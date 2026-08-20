/**
 * Internal image studio server.
 *
 * Deliberately dependency free: it serves the studio UI, stores exported crops
 * on disk and exposes a small JSON API. Uploads arrive as base64 data URLs from
 * the canvas exporter, so there is no multipart parsing to maintain.
 *
 * Configuration (environment variables)
 *   PORT               port to listen on (default 3000)
 *   HOST               interface to bind (default 0.0.0.0)
 *   UPLOAD_DIR         where images are written (default ./uploads)
 *   PUBLIC_DIR         static assets (default ./public)
 *   MAX_UPLOAD_BYTES   per-image limit (default 25 MB)
 *   ADMIN_TOKEN        when set, API calls must send `x-admin-token`
 */

import fs from 'node:fs/promises';
import http from 'node:http';
import path from 'node:path';
import { createReadStream } from 'node:fs';
import { fileURLToPath } from 'node:url';

import {
  contentTypeFor,
  extensionForMime,
  parseDataUrl,
  safeCompare,
  safeJoin,
  sanitizeFileName,
  uniqueFileName,
} from './lib/http-utils.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const projectRoot = path.resolve(here, '..');

export function readConfig(env = process.env) {
  return {
    port: Number(env.PORT ?? 3000),
    host: env.HOST ?? '0.0.0.0',
    publicDir: path.resolve(projectRoot, env.PUBLIC_DIR ?? 'public'),
    uploadDir: path.resolve(projectRoot, env.UPLOAD_DIR ?? 'uploads'),
    maxUploadBytes: Number(env.MAX_UPLOAD_BYTES ?? 25 * 1024 * 1024),
    adminToken: env.ADMIN_TOKEN ?? '',
  };
}

const JSON_BODY_LIMIT_FACTOR = 1.6; // base64 overhead plus metadata

function sendJson(response, status, payload) {
  const body = JSON.stringify(payload);
  response.writeHead(status, {
    'content-type': 'application/json; charset=utf-8',
    'content-length': Buffer.byteLength(body),
    'cache-control': 'no-store',
  });
  response.end(body);
}

function sendError(response, status, message) {
  sendJson(response, status, { error: message });
}

function readJsonBody(request, limitBytes) {
  return new Promise((resolve) => {
    const chunks = [];
    let size = 0;
    let aborted = false;

    request.on('data', (chunk) => {
      if (aborted) return;
      size += chunk.length;
      if (size > limitBytes) {
        aborted = true;
        chunks.length = 0;
        // Keep draining instead of destroying the socket: the client needs to
        // receive the 413 rather than a connection reset.
        request.resume();
        resolve({ error: 'גוף הבקשה גדול מהמותר', status: 413 });
        return;
      }
      chunks.push(chunk);
    });
    request.on('end', () => {
      if (aborted) return;
      const text = Buffer.concat(chunks).toString('utf8');
      if (!text.trim()) return resolve({ error: 'גוף הבקשה ריק', status: 400 });
      try {
        return resolve({ body: JSON.parse(text) });
      } catch {
        return resolve({ error: 'JSON לא תקין', status: 400 });
      }
    });
    request.on('error', () => {
      if (!aborted) resolve({ error: 'קריאת הבקשה נכשלה', status: 400 });
    });
  });
}

async function serveStatic(response, filePath, { immutable = false } = {}) {
  try {
    const stats = await fs.stat(filePath);
    if (stats.isDirectory()) return false;

    response.writeHead(200, {
      'content-type': contentTypeFor(filePath),
      'content-length': stats.size,
      'cache-control': immutable ? 'public, max-age=31536000, immutable' : 'no-cache',
      'x-content-type-options': 'nosniff',
      'last-modified': stats.mtime.toUTCString(),
    });
    await new Promise((resolve, reject) => {
      const stream = createReadStream(filePath);
      stream.on('error', reject);
      stream.on('end', resolve);
      stream.pipe(response);
    });
    return true;
  } catch {
    return false;
  }
}

const METADATA_SUFFIX = '.meta.json';

async function listImages(uploadDir) {
  let entries;
  try {
    entries = await fs.readdir(uploadDir, { withFileTypes: true });
  } catch {
    return [];
  }

  const images = entries.filter(
    (entry) => entry.isFile() && !entry.name.endsWith(METADATA_SUFFIX) && !entry.name.startsWith('.'),
  );

  const result = await Promise.all(
    images.map(async (entry) => {
      const filePath = path.join(uploadDir, entry.name);
      const stats = await fs.stat(filePath).catch(() => null);
      const metadata = await fs
        .readFile(`${filePath}${METADATA_SUFFIX}`, 'utf8')
        .then((text) => JSON.parse(text))
        .catch(() => null);

      return {
        name: entry.name,
        url: `/uploads/${encodeURIComponent(entry.name)}`,
        size: stats?.size ?? 0,
        modified: stats?.mtimeMs ?? 0,
        metadata,
      };
    }),
  );

  return result.sort((a, b) => b.modified - a.modified);
}

export function createApp(config) {
  const requireToken = Boolean(config.adminToken);

  const authorized = (request) => {
    if (!requireToken) return true;
    const header = request.headers['x-admin-token'];
    return safeCompare(Array.isArray(header) ? header[0] : header, config.adminToken);
  };

  return async function handle(request, response) {
    const url = new URL(request.url, `http://${request.headers.host ?? 'localhost'}`);
    const { pathname } = url;
    const method = request.method ?? 'GET';

    response.setHeader('x-content-type-options', 'nosniff');
    response.setHeader('referrer-policy', 'same-origin');

    if (pathname === '/api/config') {
      return sendJson(response, 200, {
        requiresToken: requireToken,
        maxUploadBytes: config.maxUploadBytes,
      });
    }

    if (pathname.startsWith('/api/')) {
      if (!authorized(request)) {
        return sendError(response, 401, 'נדרש מפתח גישה (x-admin-token)');
      }

      if (pathname === '/api/images' && method === 'GET') {
        return sendJson(response, 200, { images: await listImages(config.uploadDir) });
      }

      if (pathname === '/api/images' && method === 'POST') {
        return handleUpload(request, response, config);
      }

      if (pathname.startsWith('/api/images/') && method === 'DELETE') {
        return handleDelete(response, config, pathname.slice('/api/images/'.length));
      }

      return sendError(response, 404, 'נתיב API לא נמצא');
    }

    if (method !== 'GET' && method !== 'HEAD') {
      return sendError(response, 405, 'שיטת בקשה לא נתמכת');
    }

    if (pathname.startsWith('/uploads/')) {
      const target = safeJoin(config.uploadDir, pathname.slice('/uploads'.length));
      if (!target || target.endsWith(METADATA_SUFFIX)) {
        return sendError(response, 404, 'הקובץ לא נמצא');
      }
      if (await serveStatic(response, target, { immutable: true })) return undefined;
      return sendError(response, 404, 'הקובץ לא נמצא');
    }

    const relativePath = pathname === '/' ? '/index.html' : pathname;
    const target = safeJoin(config.publicDir, relativePath);
    if (target && (await serveStatic(response, target))) return undefined;

    return sendError(response, 404, 'הדף לא נמצא');
  };
}

async function handleUpload(request, response, config) {
  const limit = Math.ceil(config.maxUploadBytes * JSON_BODY_LIMIT_FACTOR) + 4096;
  const { body, error, status } = await readJsonBody(request, limit);
  if (error) return sendError(response, status, error);

  const parsed = parseDataUrl(body?.dataUrl, { maxBytes: config.maxUploadBytes });
  if (parsed.error) return sendError(response, parsed.status, parsed.error);

  await fs.mkdir(config.uploadDir, { recursive: true });
  const existing = new Set(await fs.readdir(config.uploadDir).catch(() => []));
  const desiredName = sanitizeFileName(body?.fileName, { mime: parsed.mime, fallback: 'crop' });
  const fileName = uniqueFileName(desiredName, (candidate) => existing.has(candidate));
  const filePath = path.join(config.uploadDir, fileName);

  try {
    await fs.writeFile(filePath, parsed.buffer, { flag: 'wx' });
  } catch (writeError) {
    if (writeError.code === 'EEXIST') return sendError(response, 409, 'קובץ בשם הזה כבר קיים');
    throw writeError;
  }

  const metadata = {
    savedAt: new Date().toISOString(),
    mime: parsed.mime,
    bytes: parsed.buffer.length,
    ...pickMetadata(body?.metadata),
  };
  await fs
    .writeFile(`${filePath}${METADATA_SUFFIX}`, JSON.stringify(metadata, null, 2))
    .catch(() => {});

  return sendJson(response, 201, {
    name: fileName,
    url: `/uploads/${encodeURIComponent(fileName)}`,
    size: parsed.buffer.length,
    extension: extensionForMime(parsed.mime),
    metadata,
  });
}

/** Only known, small fields are persisted; arbitrary client JSON is dropped. */
function pickMetadata(metadata) {
  if (!metadata || typeof metadata !== 'object') return {};
  const allowed = ['sourceName', 'width', 'height', 'aspect', 'preset', 'quality', 'format'];
  const result = {};
  for (const key of allowed) {
    const value = metadata[key];
    if (typeof value === 'number' && Number.isFinite(value)) result[key] = value;
    else if (typeof value === 'string' && value.length <= 200) result[key] = value;
  }
  if (metadata.transform && typeof metadata.transform === 'object') {
    const { zoom, rotation, panX, panY, flipH, flipV, fit } = metadata.transform;
    result.transform = {
      zoom: Number(zoom) || 1,
      rotation: Number(rotation) || 0,
      panX: Number(panX) || 0,
      panY: Number(panY) || 0,
      flipH: Boolean(flipH),
      flipV: Boolean(flipV),
      fit: fit === 'contain' ? 'contain' : 'cover',
    };
  }
  return result;
}

async function handleDelete(response, config, rawName) {
  const target = safeJoin(config.uploadDir, `/${rawName}`);
  if (!target || target.endsWith(METADATA_SUFFIX)) {
    return sendError(response, 400, 'שם קובץ לא תקין');
  }
  try {
    await fs.unlink(target);
    await fs.unlink(`${target}${METADATA_SUFFIX}`).catch(() => {});
    return sendJson(response, 200, { deleted: path.basename(target) });
  } catch (error) {
    if (error.code === 'ENOENT') return sendError(response, 404, 'הקובץ לא נמצא');
    throw error;
  }
}

export function createServer(config = readConfig()) {
  const app = createApp(config);
  return http.createServer((request, response) => {
    app(request, response).catch((error) => {
      console.error('[image-studio] request failed:', error);
      if (!response.headersSent) sendError(response, 500, 'שגיאת שרת פנימית');
      else response.end();
    });
  });
}

const isDirectRun = process.argv[1] && path.resolve(process.argv[1]) === fileURLToPath(import.meta.url);

if (isDirectRun) {
  const config = readConfig();
  await fs.mkdir(config.uploadDir, { recursive: true });
  createServer(config).listen(config.port, config.host, () => {
    const protection = config.adminToken ? 'מוגן במפתח גישה' : 'ללא מפתח גישה';
    console.log(`אולפן התמונות עלה: http://localhost:${config.port}  (${protection})`);
    console.log(`תיקיית שמירה: ${config.uploadDir}`);
  });
}
