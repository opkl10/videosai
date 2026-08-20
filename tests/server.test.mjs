import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import os from 'node:os';
import path from 'node:path';
import test from 'node:test';

import { createServer } from '../server/server.mjs';

const PNG_1PX_BASE64 =
  'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==';
const PNG_DATA_URL = `data:image/png;base64,${PNG_1PX_BASE64}`;

async function withServer(overrides, run) {
  const uploadDir = await fs.mkdtemp(path.join(os.tmpdir(), 'studio-uploads-'));
  const config = {
    publicDir: path.resolve(import.meta.dirname, '..', 'public'),
    uploadDir,
    maxUploadBytes: 1024 * 1024,
    adminToken: '',
    ...overrides,
  };

  const server = createServer(config);
  await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
  const base = `http://127.0.0.1:${server.address().port}`;

  try {
    await run({ base, uploadDir, config });
  } finally {
    await new Promise((resolve) => server.close(resolve));
    await fs.rm(uploadDir, { recursive: true, force: true });
  }
}

const postImage = (base, body, headers = {}) =>
  fetch(`${base}/api/images`, {
    method: 'POST',
    headers: { 'content-type': 'application/json', ...headers },
    body: JSON.stringify(body),
  });

test('config reports the upload limit and the protection state', async () => {
  await withServer({}, async ({ base }) => {
    const response = await fetch(`${base}/api/config`);
    assert.equal(response.status, 200);
    const payload = await response.json();
    assert.equal(payload.requiresToken, false);
    assert.equal(payload.maxUploadBytes, 1024 * 1024);
  });
});

test('an uploaded crop is stored, listed, served and deleted', async () => {
  await withServer({}, async ({ base, uploadDir }) => {
    const created = await postImage(base, {
      fileName: 'באנר ראשי.png',
      dataUrl: PNG_DATA_URL,
      metadata: { width: 1200, height: 630, sourceName: 'source.jpg', junk: { nope: true } },
    });
    assert.equal(created.status, 201);
    const saved = await created.json();
    assert.equal(saved.name, 'באנר-ראשי.png');
    assert.equal(saved.metadata.width, 1200);
    assert.equal(saved.metadata.junk, undefined, 'unknown metadata keys are dropped');

    const onDisk = await fs.readFile(path.join(uploadDir, saved.name));
    assert.deepEqual(onDisk, Buffer.from(PNG_1PX_BASE64, 'base64'));

    const listed = await (await fetch(`${base}/api/images`)).json();
    assert.equal(listed.images.length, 1, 'the metadata sidecar is not listed as an image');
    assert.equal(listed.images[0].name, saved.name);
    assert.equal(listed.images[0].metadata.sourceName, 'source.jpg');

    const served = await fetch(`${base}${saved.url}`);
    assert.equal(served.status, 200);
    assert.equal(served.headers.get('content-type'), 'image/png');

    const deleted = await fetch(`${base}/api/images/${encodeURIComponent(saved.name)}`, {
      method: 'DELETE',
    });
    assert.equal(deleted.status, 200);
    assert.deepEqual(await fs.readdir(uploadDir), [], 'the sidecar is removed too');
  });
});

test('uploading the same name twice keeps both files', async () => {
  await withServer({}, async ({ base }) => {
    const first = await (await postImage(base, { fileName: 'hero.png', dataUrl: PNG_DATA_URL })).json();
    const second = await (await postImage(base, { fileName: 'hero.png', dataUrl: PNG_DATA_URL })).json();
    assert.equal(first.name, 'hero.png');
    assert.equal(second.name, 'hero-2.png');
  });
});

test('path traversal in the file name cannot escape the upload directory', async () => {
  await withServer({}, async ({ base, uploadDir }) => {
    const saved = await (
      await postImage(base, { fileName: '../../evil.png', dataUrl: PNG_DATA_URL })
    ).json();
    assert.equal(saved.name, 'evil.png');
    const entries = await fs.readdir(uploadDir);
    assert.ok(entries.includes('evil.png'));
  });
});

test('non-image payloads and oversized files are refused', async () => {
  await withServer({ maxUploadBytes: 512 }, async ({ base }) => {
    const html = await postImage(base, {
      fileName: 'x.html',
      dataUrl: 'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
    });
    assert.equal(html.status, 415);

    const big = await postImage(base, {
      fileName: 'big.png',
      dataUrl: `data:image/png;base64,${Buffer.alloc(4096, 7).toString('base64')}`,
    });
    assert.equal(big.status, 413);

    const broken = await fetch(`${base}/api/images`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: '{not json',
    });
    assert.equal(broken.status, 400);
  });
});

test('metadata sidecars are never served or deleted directly', async () => {
  await withServer({}, async ({ base }) => {
    const saved = await (await postImage(base, { fileName: 'hero.png', dataUrl: PNG_DATA_URL })).json();
    const sidecar = await fetch(`${base}/uploads/${saved.name}.meta.json`);
    assert.equal(sidecar.status, 404);

    const removal = await fetch(`${base}/api/images/${encodeURIComponent(`${saved.name}.meta.json`)}`, {
      method: 'DELETE',
    });
    assert.equal(removal.status, 400);
  });
});

test('serving static files stays inside the public directory', async () => {
  await withServer({}, async ({ base }) => {
    const index = await fetch(`${base}/`);
    assert.equal(index.status, 200);
    assert.match(index.headers.get('content-type'), /text\/html/);
    assert.match(await index.text(), /אולפן התמונות/);

    const script = await fetch(`${base}/js/cropper.js`);
    assert.equal(script.status, 200);
    assert.match(script.headers.get('content-type'), /javascript/);

    const escaped = await fetch(`${base}/../package.json`);
    assert.equal(escaped.status, 404);

    const missing = await fetch(`${base}/api/unknown`);
    assert.equal(missing.status, 404);
  });
});

test('a configured token guards the API but not the config endpoint', async () => {
  await withServer({ adminToken: 'top-secret' }, async ({ base }) => {
    const config = await (await fetch(`${base}/api/config`)).json();
    assert.equal(config.requiresToken, true);

    const anonymous = await fetch(`${base}/api/images`);
    assert.equal(anonymous.status, 401);

    const wrongToken = await postImage(
      base,
      { fileName: 'x.png', dataUrl: PNG_DATA_URL },
      { 'x-admin-token': 'guess' },
    );
    assert.equal(wrongToken.status, 401);

    const allowed = await postImage(
      base,
      { fileName: 'x.png', dataUrl: PNG_DATA_URL },
      { 'x-admin-token': 'top-secret' },
    );
    assert.equal(allowed.status, 201);
  });
});

test('unsupported methods on static paths are rejected', async () => {
  await withServer({}, async ({ base }) => {
    const response = await fetch(`${base}/`, { method: 'PUT' });
    assert.equal(response.status, 405);
  });
});
