import path from 'node:path';

export const IMAGE_MIME_TO_EXTENSION = Object.freeze({
  'image/jpeg': '.jpg',
  'image/png': '.png',
  'image/webp': '.webp',
  'image/avif': '.avif',
  'image/gif': '.gif',
});

const STATIC_CONTENT_TYPES = Object.freeze({
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.mjs': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2',
  '.map': 'application/json; charset=utf-8',
  ...Object.fromEntries(
    Object.entries(IMAGE_MIME_TO_EXTENSION).map(([mime, extension]) => [extension, mime]),
  ),
  '.jpeg': 'image/jpeg',
});

export function contentTypeFor(filePath) {
  return STATIC_CONTENT_TYPES[path.extname(filePath).toLowerCase()] ?? 'application/octet-stream';
}

export function isAllowedImageMime(mime) {
  return Object.hasOwn(IMAGE_MIME_TO_EXTENSION, mime);
}

export function extensionForMime(mime) {
  return IMAGE_MIME_TO_EXTENSION[mime] ?? '';
}

/**
 * Turns a client supplied name into something safe to write to disk: no
 * directory parts, no control characters, bounded length, and an extension that
 * matches the actual image type.
 *
 * Unicode letters are kept so Hebrew file names survive.
 */
export function sanitizeFileName(rawName, { mime, fallback = 'image' } = {}) {
  const base = path.basename(String(rawName ?? '')).normalize('NFC');
  const extension = extensionForMime(mime);
  const withoutExtension = base.replace(/\.[^.]{1,8}$/u, '');

  let cleaned = withoutExtension
    .replace(/[\u0000-\u001f\u007f]/gu, '')
    .replace(/[^\p{L}\p{N}._-]+/gu, '-')
    .replace(/-{2,}/gu, '-')
    .replace(/^[-._]+|[-._]+$/gu, '');

  if (!cleaned) cleaned = fallback;
  if (cleaned.length > 80) cleaned = cleaned.slice(0, 80).replace(/[-._]+$/u, '') || fallback;

  return `${cleaned}${extension}`;
}

/** Adds `-2`, `-3`, … until `exists(name)` is false. */
export function uniqueFileName(name, exists) {
  if (!exists(name)) return name;
  const extension = path.extname(name);
  const stem = name.slice(0, name.length - extension.length);
  for (let counter = 2; counter < 10000; counter += 1) {
    const candidate = `${stem}-${counter}${extension}`;
    if (!exists(candidate)) return candidate;
  }
  return `${stem}-${Date.now()}${extension}`;
}

const DATA_URL_PATTERN = /^data:([a-z]+\/[a-z0-9.+-]+);base64,([\s\S]+)$/iu;

/** Decodes a base64 data URL into `{ mime, buffer }`, validating type and size. */
export function parseDataUrl(dataUrl, { maxBytes = Infinity } = {}) {
  if (typeof dataUrl !== 'string') {
    return { error: 'שדה dataUrl חסר או שאינו מחרוזת', status: 400 };
  }
  const match = DATA_URL_PATTERN.exec(dataUrl.trim());
  if (!match) return { error: 'פורמט dataUrl אינו תקין', status: 400 };

  const mime = match[1].toLowerCase();
  if (!isAllowedImageMime(mime)) {
    return { error: `סוג קובץ לא נתמך: ${mime}`, status: 415 };
  }

  const base64 = match[2].replace(/\s+/gu, '');
  // Base64 grows the payload by 4/3, so reject before allocating the buffer.
  if ((base64.length * 3) / 4 > maxBytes + 1024) {
    return { error: 'הקובץ גדול מהמותר', status: 413 };
  }

  let buffer;
  try {
    buffer = Buffer.from(base64, 'base64');
  } catch {
    return { error: 'פענוח base64 נכשל', status: 400 };
  }
  if (!buffer.length) return { error: 'הקובץ ריק', status: 400 };
  if (buffer.length > maxBytes) return { error: 'הקובץ גדול מהמותר', status: 413 };

  return { mime, buffer };
}

/**
 * Resolves a URL path inside `root`, or returns null when the path is not
 * usable. Traversal is rejected rather than silently normalised, so a bad
 * request never quietly turns into a different file.
 */
export function safeJoin(root, urlPath) {
  let decoded;
  try {
    decoded = decodeURIComponent(urlPath);
  } catch {
    return null;
  }
  if (decoded.includes('\0')) return null;

  const segments = decoded.split(/[/\\]+/u).filter((segment) => segment && segment !== '.');
  if (segments.some((segment) => segment === '..')) return null;

  const resolvedRoot = path.resolve(root);
  const target = path.resolve(resolvedRoot, ...segments);
  if (target !== resolvedRoot && !target.startsWith(resolvedRoot + path.sep)) return null;
  return target;
}

/** Constant-time-ish comparison so token checks do not leak length by timing. */
export function safeCompare(a, b) {
  const left = String(a ?? '');
  const right = String(b ?? '');
  let mismatch = left.length === right.length ? 0 : 1;
  const length = Math.max(left.length, right.length, 1);
  for (let index = 0; index < length; index += 1) {
    mismatch |= left.charCodeAt(index % (left.length || 1))
      ^ right.charCodeAt(index % (right.length || 1));
  }
  return mismatch === 0;
}
