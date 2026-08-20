import assert from 'node:assert/strict';
import test from 'node:test';

import {
  ADJUSTMENTS,
  DEFAULT_ADJUSTMENTS,
  applyAdjustmentsToPixels,
  isNeutralAdjustments,
  normalizeAdjustments,
  toCssFilter,
} from '../public/js/filters.js';

function pixels(colors) {
  const data = new Uint8ClampedArray(colors.length * 4);
  colors.forEach(([r, g, b, a = 255], index) => {
    data.set([r, g, b, a], index * 4);
  });
  return { data, width: colors.length, height: 1 };
}

test('defaults are neutral and produce no filter string', () => {
  assert.equal(isNeutralAdjustments(DEFAULT_ADJUSTMENTS), true);
  assert.equal(toCssFilter(DEFAULT_ADJUSTMENTS), 'none');
  assert.equal(toCssFilter({}), 'none');
});

test('normalizeAdjustments clamps and fills missing keys', () => {
  const normalized = normalizeAdjustments({ brightness: 1000, contrast: -50, blur: 'x' });
  assert.equal(normalized.brightness, 200);
  assert.equal(normalized.contrast, 20);
  assert.equal(normalized.blur, 0);
  assert.equal(Object.keys(normalized).length, ADJUSTMENTS.length);
});

test('toCssFilter lists only the changed values, in spec order', () => {
  const filter = toCssFilter({ sepia: 30, brightness: 120, saturation: 150 });
  assert.equal(filter, 'brightness(120%) saturate(150%) sepia(30%)');
});

test('brightness scales channels and clamps at the top', () => {
  const image = pixels([[100, 100, 100], [200, 200, 200]]);
  applyAdjustmentsToPixels(image, { brightness: 150 });
  assert.equal(image.data[0], 150);
  assert.equal(image.data[4], 255, 'clamped rather than wrapped');
});

test('contrast pivots around mid grey', () => {
  const image = pixels([[128, 128, 128], [200, 100, 60]]);
  applyAdjustmentsToPixels(image, { contrast: 200 });
  assert.equal(image.data[0], 128, 'mid grey is the fixed point');
  assert.ok(image.data[4] > 200, 'bright channels get brighter');
  assert.ok(image.data[6] < 60, 'dark channels get darker');
});

test('full grayscale collapses channels to a single luminance', () => {
  const image = pixels([[200, 40, 90]]);
  applyAdjustmentsToPixels(image, { grayscale: 100 });
  const [r, g, b] = image.data;
  assert.equal(r, g);
  assert.equal(g, b);
  const expected = Math.round(0.2126 * 200 + 0.7152 * 40 + 0.0722 * 90);
  assert.ok(Math.abs(r - expected) <= 1, `expected about ${expected}, got ${r}`);
});

test('zero saturation matches full grayscale', () => {
  const desaturated = pixels([[200, 40, 90]]);
  const grey = pixels([[200, 40, 90]]);
  applyAdjustmentsToPixels(desaturated, { saturation: 0 });
  applyAdjustmentsToPixels(grey, { grayscale: 100 });
  assert.deepEqual([...desaturated.data], [...grey.data]);
});

test('alpha is preserved by colour adjustments', () => {
  const image = pixels([[10, 20, 30, 77]]);
  applyAdjustmentsToPixels(image, { brightness: 180, contrast: 140, sepia: 60 });
  assert.equal(image.data[3], 77);
});

test('neutral adjustments leave pixels untouched', () => {
  const image = pixels([[1, 2, 3], [250, 251, 252]]);
  const before = [...image.data];
  applyAdjustmentsToPixels(image, DEFAULT_ADJUSTMENTS);
  assert.deepEqual([...image.data], before);
});

test('blur spreads a single bright pixel to its neighbours', () => {
  const width = 9;
  const data = new Uint8ClampedArray(width * width * 4);
  const centre = (4 * width + 4) * 4;
  data.set([255, 255, 255, 255], centre);
  const image = { data, width, height: width };

  applyAdjustmentsToPixels(image, { blur: 2 });
  const neighbour = (4 * width + 5) * 4;
  assert.ok(image.data[centre] < 255, 'the centre is dimmed');
  assert.ok(image.data[neighbour] > 0, 'light bleeds into the neighbour');
});
