import assert from 'node:assert/strict';
import path from 'node:path';
import test from 'node:test';

import {
  contentTypeFor,
  extensionForMime,
  isAllowedImageMime,
  parseDataUrl,
  safeCompare,
  safeJoin,
  sanitizeFileName,
  uniqueFileName,
} from '../server/lib/http-utils.mjs';

test('content types cover the studio assets', () => {
  assert.equal(contentTypeFor('/index.html'), 'text/html; charset=utf-8');
  assert.equal(contentTypeFor('js/app.js'), 'text/javascript; charset=utf-8');
  assert.equal(contentTypeFor('a/b/crop.webp'), 'image/webp');
  assert.equal(contentTypeFor('mystery.bin'), 'application/octet-stream');
});

test('only image types are accepted', () => {
  assert.equal(isAllowedImageMime('image/png'), true);
  assert.equal(isAllowedImageMime('text/html'), false);
  assert.equal(extensionForMime('image/jpeg'), '.jpg');
  assert.equal(extensionForMime('application/pdf'), '');
});

test('sanitizeFileName strips paths and forces the real extension', () => {
  assert.equal(sanitizeFileName('../../etc/passwd', { mime: 'image/png' }), 'passwd.png');
  assert.equal(sanitizeFileName('banner.jpg', { mime: 'image/webp' }), 'banner.webp');
  assert.equal(sanitizeFileName('a b/c*d?.png', { mime: 'image/png' }), 'c-d.png');
  assert.equal(sanitizeFileName('', { mime: 'image/png', fallback: 'crop' }), 'crop.png');
  assert.equal(sanitizeFileName('...', { mime: 'image/png', fallback: 'crop' }), 'crop.png');
});

test('sanitizeFileName keeps Hebrew names', () => {
  assert.equal(sanitizeFileName('באנר-ראשי.png', { mime: 'image/png' }), 'באנר-ראשי.png');
});

test('sanitizeFileName bounds the length', () => {
  const name = sanitizeFileName('a'.repeat(300), { mime: 'image/png' });
  assert.ok(name.length <= 84, `unexpected length ${name.length}`);
  assert.ok(name.endsWith('.png'));
});

test('uniqueFileName appends a counter next to the extension', () => {
  const taken = new Set(['hero.png', 'hero-2.png']);
  assert.equal(uniqueFileName('hero.png', (name) => taken.has(name)), 'hero-3.png');
  assert.equal(uniqueFileName('fresh.png', (name) => taken.has(name)), 'fresh.png');
});

test('parseDataUrl decodes valid image payloads', () => {
  const payload = Buffer.from('fake-png-bytes');
  const result = parseDataUrl(`data:image/png;base64,${payload.toString('base64')}`);
  assert.equal(result.mime, 'image/png');
  assert.deepEqual(result.buffer, payload);
});

test('parseDataUrl rejects bad input', () => {
  assert.equal(parseDataUrl(undefined).status, 400);
  assert.equal(parseDataUrl('not-a-data-url').status, 400);
  assert.equal(parseDataUrl('data:text/html;base64,PHNjcmlwdD4=').status, 415);
  assert.equal(parseDataUrl('data:image/png;base64,').status, 400);

  const big = `data:image/png;base64,${Buffer.alloc(4096, 1).toString('base64')}`;
  assert.equal(parseDataUrl(big, { maxBytes: 1024 }).status, 413);
});

test('safeJoin blocks traversal outside the root', () => {
  const root = path.resolve('/tmp/uploads');
  assert.equal(safeJoin(root, '/photo.png'), path.join(root, 'photo.png'));
  assert.equal(safeJoin(root, '/nested/photo.png'), path.join(root, 'nested/photo.png'));
  assert.equal(safeJoin(root, '/../secrets.txt'), null);
  assert.equal(safeJoin(root, '/..%2f..%2fetc/passwd'), null);
  assert.equal(safeJoin(root, '/%00.png'), null);
  assert.equal(safeJoin(root, '/%ZZ'), null);
});

test('safeJoin decodes percent encoded names', () => {
  const root = path.resolve('/tmp/uploads');
  assert.equal(safeJoin(root, `/${encodeURIComponent('באנר.png')}`), path.join(root, 'באנר.png'));
});

test('safeCompare matches only identical strings', () => {
  assert.equal(safeCompare('secret', 'secret'), true);
  assert.equal(safeCompare('secret', 'secre'), false);
  assert.equal(safeCompare('secret', 'Secret'), false);
  assert.equal(safeCompare(undefined, 'secret'), false);
  assert.equal(safeCompare('', ''), true);
});
