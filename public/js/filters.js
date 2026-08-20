/**
 * Colour adjustments shared by the live preview and the exporter.
 *
 * The preview uses a CSS/canvas `filter` string. Export prefers the same string
 * via `CanvasRenderingContext2D.filter`, and falls back to the pixel
 * implementation below on engines that do not support it, so a downloaded file
 * always matches what the user saw.
 *
 * Both paths follow the CSS Filter Effects spec and apply the operations in the
 * same order: brightness, contrast, saturation, grayscale, sepia, blur.
 */

export const ADJUSTMENTS = [
  { key: 'brightness', label: 'בהירות', min: 20, max: 200, step: 1, neutral: 100, unit: '%' },
  { key: 'contrast', label: 'ניגודיות', min: 20, max: 200, step: 1, neutral: 100, unit: '%' },
  { key: 'saturation', label: 'רוויה', min: 0, max: 300, step: 1, neutral: 100, unit: '%' },
  { key: 'grayscale', label: 'שחור־לבן', min: 0, max: 100, step: 1, neutral: 0, unit: '%' },
  { key: 'sepia', label: 'ספיה', min: 0, max: 100, step: 1, neutral: 0, unit: '%' },
  { key: 'blur', label: 'טשטוש', min: 0, max: 20, step: 0.1, neutral: 0, unit: 'px' },
];

export const DEFAULT_ADJUSTMENTS = Object.freeze(
  Object.fromEntries(ADJUSTMENTS.map((item) => [item.key, item.neutral])),
);

export function normalizeAdjustments(values = {}) {
  const result = {};
  for (const { key, min, max, neutral } of ADJUSTMENTS) {
    const value = Number(values[key]);
    result[key] = Number.isFinite(value) ? Math.min(max, Math.max(min, value)) : neutral;
  }
  return result;
}

export function isNeutralAdjustments(values = {}) {
  const adjustments = normalizeAdjustments(values);
  return ADJUSTMENTS.every(({ key, neutral }) => Math.abs(adjustments[key] - neutral) < 1e-6);
}

/** Builds a CSS filter string, or `'none'` when nothing needs to change. */
export function toCssFilter(values = {}) {
  const adjustments = normalizeAdjustments(values);
  if (isNeutralAdjustments(adjustments)) return 'none';

  const parts = [];
  if (adjustments.brightness !== 100) parts.push(`brightness(${adjustments.brightness}%)`);
  if (adjustments.contrast !== 100) parts.push(`contrast(${adjustments.contrast}%)`);
  if (adjustments.saturation !== 100) parts.push(`saturate(${adjustments.saturation}%)`);
  if (adjustments.grayscale !== 0) parts.push(`grayscale(${adjustments.grayscale}%)`);
  if (adjustments.sepia !== 0) parts.push(`sepia(${adjustments.sepia}%)`);
  if (adjustments.blur !== 0) parts.push(`blur(${adjustments.blur}px)`);
  return parts.length ? parts.join(' ') : 'none';
}

const LUMA_R = 0.2126;
const LUMA_G = 0.7152;
const LUMA_B = 0.0722;

function saturationMatrix(amount) {
  const inverse = 1 - amount;
  return [
    LUMA_R * inverse + amount, LUMA_G * inverse, LUMA_B * inverse,
    LUMA_R * inverse, LUMA_G * inverse + amount, LUMA_B * inverse,
    LUMA_R * inverse, LUMA_G * inverse, LUMA_B * inverse + amount,
  ];
}

function sepiaMatrix(amount) {
  const inverse = 1 - amount;
  return [
    0.393 * amount + inverse, 0.769 * amount, 0.189 * amount,
    0.349 * amount, 0.686 * amount + inverse, 0.168 * amount,
    0.272 * amount, 0.534 * amount, 0.131 * amount + inverse,
  ];
}

function applyMatrix(data, matrix) {
  for (let index = 0; index < data.length; index += 4) {
    const r = data[index];
    const g = data[index + 1];
    const b = data[index + 2];
    data[index] = matrix[0] * r + matrix[1] * g + matrix[2] * b;
    data[index + 1] = matrix[3] * r + matrix[4] * g + matrix[5] * b;
    data[index + 2] = matrix[6] * r + matrix[7] * g + matrix[8] * b;
  }
}

/** Separable box blur pass; three passes approximate a Gaussian closely enough. */
function boxBlurPass(data, width, height, radius) {
  if (radius < 1) return;
  const window = radius * 2 + 1;
  const scratch = new Float32Array(data.length);

  for (let channel = 0; channel < 4; channel += 1) {
    for (let y = 0; y < height; y += 1) {
      const row = y * width * 4;
      let sum = 0;
      for (let x = -radius; x <= radius; x += 1) {
        const clampedX = Math.min(width - 1, Math.max(0, x));
        sum += data[row + clampedX * 4 + channel];
      }
      for (let x = 0; x < width; x += 1) {
        scratch[row + x * 4 + channel] = sum / window;
        const outgoing = Math.min(width - 1, Math.max(0, x - radius));
        const incoming = Math.min(width - 1, Math.max(0, x + radius + 1));
        sum += data[row + incoming * 4 + channel] - data[row + outgoing * 4 + channel];
      }
    }

    for (let x = 0; x < width; x += 1) {
      let sum = 0;
      for (let y = -radius; y <= radius; y += 1) {
        const clampedY = Math.min(height - 1, Math.max(0, y));
        sum += scratch[clampedY * width * 4 + x * 4 + channel];
      }
      for (let y = 0; y < height; y += 1) {
        data[y * width * 4 + x * 4 + channel] = sum / window;
        const outgoing = Math.min(height - 1, Math.max(0, y - radius));
        const incoming = Math.min(height - 1, Math.max(0, y + radius + 1));
        sum += scratch[incoming * width * 4 + x * 4 + channel]
          - scratch[outgoing * width * 4 + x * 4 + channel];
      }
    }
  }
}

/**
 * Applies the adjustments to raw RGBA pixels in place.
 * `pixels` may be an `ImageData` or `{ data, width, height }`.
 */
export function applyAdjustmentsToPixels(pixels, values = {}) {
  const adjustments = normalizeAdjustments(values);
  if (isNeutralAdjustments(adjustments)) return pixels;

  const { data, width, height } = pixels;
  const brightness = adjustments.brightness / 100;
  const contrast = adjustments.contrast / 100;
  const intercept = (0.5 - contrast * 0.5) * 255;

  // Each stage is written back separately so that clamping between stages
  // matches the CSS pipeline.
  if (brightness !== 1) {
    for (let index = 0; index < data.length; index += 4) {
      data[index] *= brightness;
      data[index + 1] *= brightness;
      data[index + 2] *= brightness;
    }
  }

  if (contrast !== 1) {
    for (let index = 0; index < data.length; index += 4) {
      data[index] = data[index] * contrast + intercept;
      data[index + 1] = data[index + 1] * contrast + intercept;
      data[index + 2] = data[index + 2] * contrast + intercept;
    }
  }

  if (adjustments.saturation !== 100) applyMatrix(data, saturationMatrix(adjustments.saturation / 100));
  if (adjustments.grayscale !== 0) applyMatrix(data, saturationMatrix(1 - adjustments.grayscale / 100));
  if (adjustments.sepia !== 0) applyMatrix(data, sepiaMatrix(adjustments.sepia / 100));

  if (adjustments.blur > 0 && width > 0 && height > 0) {
    const radius = Math.round(adjustments.blur * 1.5);
    for (let pass = 0; pass < 3; pass += 1) boxBlurPass(data, width, height, radius);
  }

  return pixels;
}
