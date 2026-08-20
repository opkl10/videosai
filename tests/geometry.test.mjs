import assert from 'node:assert/strict';
import test from 'node:test';

import {
  clamp,
  clampOffset,
  containScale,
  fitBox,
  formatBytes,
  minimumCoverScale,
  normalizeAngle,
  normalizedCropRect,
  offsetForRotationAroundCentre,
  offsetForScaleAroundAnchor,
  outputSize,
  panLimits,
  parseAspect,
  rotatePoint,
  rotatedHalfExtent,
} from '../public/js/geometry.js';

const close = (actual, expected, tolerance = 1e-6) =>
  assert.ok(
    Math.abs(actual - expected) <= tolerance,
    `expected ${actual} to be within ${tolerance} of ${expected}`,
  );

test('clamp keeps values inside the range and centres inverted ranges', () => {
  assert.equal(clamp(5, 0, 10), 5);
  assert.equal(clamp(-3, 0, 10), 0);
  assert.equal(clamp(30, 0, 10), 10);
  assert.equal(clamp(7, 4, -4), 0, 'an image smaller than the frame is centred');
  assert.equal(clamp(Number.NaN, 2, 8), 2);
});

test('normalizeAngle wraps into (-180, 180]', () => {
  assert.equal(normalizeAngle(0), 0);
  assert.equal(normalizeAngle(360), 0);
  assert.equal(normalizeAngle(190), -170);
  assert.equal(normalizeAngle(-190), 170);
  assert.equal(normalizeAngle(180), 180);
  assert.equal(normalizeAngle(540), 180);
});

test('rotatePoint round trips', () => {
  const rotated = rotatePoint(10, -4, 37);
  const back = rotatePoint(rotated.x, rotated.y, -37);
  close(back.x, 10, 1e-9);
  close(back.y, -4, 1e-9);
});

test('rotatedHalfExtent grows a square rotated by 45 degrees', () => {
  const straight = rotatedHalfExtent(50, 50, 0);
  close(straight.halfWidth, 50);
  close(straight.halfHeight, 50);

  const diagonal = rotatedHalfExtent(50, 50, 45);
  close(diagonal.halfWidth, 50 * Math.SQRT2, 1e-9);
  close(diagonal.halfHeight, 50 * Math.SQRT2, 1e-9);
});

test('fitBox centres the largest rectangle of the requested aspect', () => {
  const wide = fitBox({ containerWidth: 400, containerHeight: 300, aspect: 16 / 9 });
  close(wide.width, 400);
  close(wide.height, 225);
  close(wide.x, 0);
  close(wide.y, 37.5);

  const tall = fitBox({ containerWidth: 400, containerHeight: 300, aspect: 1 / 2 });
  close(tall.height, 300);
  close(tall.width, 150);
  close(tall.x, 125);

  const padded = fitBox({ containerWidth: 400, containerHeight: 400, aspect: 1, padding: 20 });
  close(padded.width, 360);
  close(padded.x, 20);
});

test('minimumCoverScale covers the frame exactly on the tight axis', () => {
  const scale = minimumCoverScale({
    imageWidth: 1000,
    imageHeight: 500,
    cropWidth: 400,
    cropHeight: 400,
  });
  close(scale, 400 / 500, 1e-9);

  const rotated = minimumCoverScale({
    imageWidth: 1000,
    imageHeight: 1000,
    cropWidth: 100,
    cropHeight: 100,
    rotation: 45,
  });
  close(rotated, (100 * Math.SQRT2) / 1000, 1e-9);
});

test('a square image at cover scale leaves no pan freedom', () => {
  const geometry = { imageWidth: 800, imageHeight: 800, cropWidth: 300, cropHeight: 300 };
  const scale = minimumCoverScale(geometry);
  const limits = panLimits({ ...geometry, scale });
  close(limits.x, 0, 1e-9);
  close(limits.y, 0, 1e-9);
});

test('containScale fits the whole image inside the frame', () => {
  const scale = containScale({
    imageWidth: 1000,
    imageHeight: 500,
    cropWidth: 400,
    cropHeight: 400,
  });
  close(scale, 0.4, 1e-9);
});

test('clampOffset never lets the frame slide off the image', () => {
  const geometry = {
    imageWidth: 1000,
    imageHeight: 800,
    cropWidth: 400,
    cropHeight: 400,
    rotation: 0,
    scale: 1,
  };
  const limits = panLimits(geometry);
  close(limits.x, 300);
  close(limits.y, 200);

  const pushed = clampOffset({ ...geometry, offsetX: 5000, offsetY: -5000 });
  close(pushed.offsetX, 300);
  close(pushed.offsetY, -200);

  const inside = clampOffset({ ...geometry, offsetX: 120, offsetY: -90 });
  close(inside.offsetX, 120);
  close(inside.offsetY, -90);
});

test('clampOffset respects rotation by working in the image frame', () => {
  const geometry = {
    imageWidth: 1000,
    imageHeight: 1000,
    cropWidth: 200,
    cropHeight: 200,
    rotation: 45,
    scale: 1,
  };
  const clamped = clampOffset({ ...geometry, offsetX: 10000, offsetY: 0 });
  const local = rotatePoint(clamped.offsetX, clamped.offsetY, -45);
  const limits = panLimits(geometry);
  close(Math.abs(local.x), limits.x, 1e-6);
  assert.ok(limits.x > 0, 'a 1000px image still has room around a rotated 200px frame');
});

test('clampOffset centres an image that is smaller than the frame', () => {
  const clamped = clampOffset({
    imageWidth: 100,
    imageHeight: 100,
    cropWidth: 400,
    cropHeight: 400,
    scale: 1,
    offsetX: 90,
    offsetY: -70,
  });
  close(clamped.offsetX, 0, 1e-9);
  close(clamped.offsetY, 0, 1e-9);
});

test('offsetForScaleAroundAnchor keeps the anchored point still', () => {
  const result = offsetForScaleAroundAnchor({
    offsetX: 40,
    offsetY: -20,
    fromScale: 1,
    toScale: 2,
    anchorX: 100,
    anchorY: 50,
  });
  // The image point under the anchor must map to the same screen position.
  const before = { x: (100 - 40) / 1, y: (50 - -20) / 1 };
  const after = { x: (100 - result.offsetX) / 2, y: (50 - result.offsetY) / 2 };
  close(after.x, before.x, 1e-9);
  close(after.y, before.y, 1e-9);
});

test('offsetForRotationAroundCentre rotates the offset itself', () => {
  const result = offsetForRotationAroundCentre({ offsetX: 10, offsetY: 0, deltaDegrees: 90 });
  close(result.offsetX, 0, 1e-9);
  close(result.offsetY, 10, 1e-9);
});

test('parseAspect understands ratios, fractions and plain numbers', () => {
  close(parseAspect('16:9'), 16 / 9, 1e-9);
  close(parseAspect('16/9'), 16 / 9, 1e-9);
  close(parseAspect('1.5'), 1.5);
  close(parseAspect(2), 2);
  assert.equal(parseAspect('', 3), 3);
  assert.equal(parseAspect('0:5', 3), 3);
  assert.equal(parseAspect('abc', 3), 3);
});

test('outputSize follows the aspect ratio and caps huge requests', () => {
  const wide = outputSize({ aspect: 16 / 9, width: 1920 });
  assert.deepEqual(wide, { width: 1920, height: 1080 });

  const capped = outputSize({ aspect: 1, width: 50000 });
  assert.ok(capped.width <= 12000, 'the longest side is capped');
  assert.equal(capped.width, capped.height);

  const area = outputSize({ aspect: 4, width: 20000, maxSide: 100000, maxPixels: 1e6 });
  assert.ok(area.width * area.height <= 1.01e6, 'total pixels are capped');
});

test('normalizedCropRect describes the visible part of the source image', () => {
  const full = normalizedCropRect({
    imageWidth: 1000,
    imageHeight: 500,
    cropWidth: 400,
    cropHeight: 200,
    scale: 0.4,
  });
  close(full.x, 0, 1e-9);
  close(full.y, 0, 1e-9);
  close(full.width, 1, 1e-9);
  close(full.height, 1, 1e-9);

  const zoomed = normalizedCropRect({
    imageWidth: 1000,
    imageHeight: 500,
    cropWidth: 400,
    cropHeight: 200,
    scale: 0.8,
  });
  close(zoomed.width, 0.5, 1e-9);
  close(zoomed.x, 0.25, 1e-9);
});

test('formatBytes stays readable', () => {
  assert.equal(formatBytes(512), '512 B');
  assert.equal(formatBytes(2048), '2.0 KB');
  assert.equal(formatBytes(5 * 1024 * 1024), '5.0 MB');
  assert.equal(formatBytes(-1), '—');
});
