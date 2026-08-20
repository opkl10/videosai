/**
 * Pure geometry helpers for the crop stage.
 *
 * Coordinate model
 * ----------------
 * The crop frame is an axis-aligned rectangle on screen. The image is painted
 * with the transform `translate(offset) · rotate(rotation) · scale(±scale)`
 * around the frame centre, so `offset` is measured in screen pixels from the
 * centre of the frame to the centre of the image.
 *
 * Every function here is side-effect free so it can be unit tested in Node
 * without a DOM.
 */

export const DEG_TO_RAD = Math.PI / 180;

/** Clamps `value`; an inverted range collapses to its midpoint instead of throwing. */
export function clamp(value, min, max) {
  if (!Number.isFinite(value)) return min;
  if (max < min) return (min + max) / 2;
  return Math.min(max, Math.max(min, value));
}

/** Wraps an angle into the (-180, 180] range. */
export function normalizeAngle(degrees) {
  if (!Number.isFinite(degrees)) return 0;
  let angle = degrees % 360;
  if (angle > 180) angle -= 360;
  if (angle <= -180) angle += 360;
  return angle;
}

export function rotatePoint(x, y, degrees) {
  const rad = degrees * DEG_TO_RAD;
  const cos = Math.cos(rad);
  const sin = Math.sin(rad);
  return { x: x * cos - y * sin, y: x * sin + y * cos };
}

/** Half extents of the axis-aligned bounding box of a rectangle rotated by `degrees`. */
export function rotatedHalfExtent(halfWidth, halfHeight, degrees) {
  const rad = degrees * DEG_TO_RAD;
  const cos = Math.abs(Math.cos(rad));
  const sin = Math.abs(Math.sin(rad));
  return {
    halfWidth: halfWidth * cos + halfHeight * sin,
    halfHeight: halfWidth * sin + halfHeight * cos,
  };
}

/** Largest rectangle of the given aspect ratio that fits in the container, centred. */
export function fitBox({ containerWidth, containerHeight, aspect, padding = 0 }) {
  const availableWidth = Math.max(0, containerWidth - padding * 2);
  const availableHeight = Math.max(0, containerHeight - padding * 2);
  const ratio = Number.isFinite(aspect) && aspect > 0 ? aspect : 1;

  let width = availableWidth;
  let height = width / ratio;
  if (height > availableHeight) {
    height = availableHeight;
    width = height * ratio;
  }

  return {
    x: (containerWidth - width) / 2,
    y: (containerHeight - height) / 2,
    width,
    height,
  };
}

/**
 * Smallest scale at which the rotated image still covers the whole crop frame.
 *
 * The frame is projected into the (rotated) frame of the image, and the image
 * rectangle must contain that projection.
 */
export function minimumCoverScale({
  imageWidth,
  imageHeight,
  cropWidth,
  cropHeight,
  rotation = 0,
}) {
  if (!(imageWidth > 0) || !(imageHeight > 0)) return 1;
  const { halfWidth, halfHeight } = rotatedHalfExtent(cropWidth / 2, cropHeight / 2, rotation);
  return Math.max((halfWidth * 2) / imageWidth, (halfHeight * 2) / imageHeight);
}

/** Scale at which the whole image is visible inside the crop frame ("contain"). */
export function containScale({ imageWidth, imageHeight, cropWidth, cropHeight, rotation = 0 }) {
  if (!(imageWidth > 0) || !(imageHeight > 0)) return 1;
  const { halfWidth, halfHeight } = rotatedHalfExtent(imageWidth / 2, imageHeight / 2, rotation);
  return Math.min(cropWidth / (halfWidth * 2), cropHeight / (halfHeight * 2));
}

/**
 * Maximum pan distance, expressed in the image's own rotated frame.
 * A negative value means the image is too small to cover the frame on that axis.
 */
export function panLimits({
  imageWidth,
  imageHeight,
  cropWidth,
  cropHeight,
  rotation = 0,
  scale = 1,
}) {
  const { halfWidth, halfHeight } = rotatedHalfExtent(cropWidth / 2, cropHeight / 2, rotation);
  return {
    x: (imageWidth * scale) / 2 - halfWidth,
    y: (imageHeight * scale) / 2 - halfHeight,
  };
}

/** Keeps the image covering the crop frame, returning a corrected screen-space offset. */
export function clampOffset({
  imageWidth,
  imageHeight,
  cropWidth,
  cropHeight,
  rotation = 0,
  scale = 1,
  offsetX = 0,
  offsetY = 0,
}) {
  const limits = panLimits({ imageWidth, imageHeight, cropWidth, cropHeight, rotation, scale });
  const local = rotatePoint(offsetX, offsetY, -rotation);
  const clamped = rotatePoint(
    clamp(local.x, -limits.x, limits.x),
    clamp(local.y, -limits.y, limits.y),
    rotation,
  );
  return { offsetX: clamped.x, offsetY: clamped.y };
}

/**
 * Offset that keeps the image point currently under `anchor` pinned while the
 * scale changes from `fromScale` to `toScale`. `anchor` is relative to the
 * centre of the crop frame.
 */
export function offsetForScaleAroundAnchor({
  offsetX,
  offsetY,
  fromScale,
  toScale,
  anchorX = 0,
  anchorY = 0,
}) {
  if (!(fromScale > 0)) return { offsetX, offsetY };
  const factor = toScale / fromScale;
  return {
    offsetX: anchorX - (anchorX - offsetX) * factor,
    offsetY: anchorY - (anchorY - offsetY) * factor,
  };
}

/**
 * Rotating the image around the centre of the crop frame (rather than around
 * its own centre) keeps whatever the user is looking at in place.
 */
export function offsetForRotationAroundCentre({ offsetX, offsetY, deltaDegrees }) {
  const rotated = rotatePoint(offsetX, offsetY, deltaDegrees);
  return { offsetX: rotated.x, offsetY: rotated.y };
}

/** Parses "16:9", "16/9", "1.777" or a number into an aspect ratio. */
export function parseAspect(input, fallback = 1) {
  if (typeof input === 'number') return input > 0 ? input : fallback;
  if (typeof input !== 'string') return fallback;
  const text = input.trim();
  if (!text) return fallback;

  const parts = text.split(/[:/]/);
  if (parts.length === 2) {
    const width = Number.parseFloat(parts[0]);
    const height = Number.parseFloat(parts[1]);
    if (width > 0 && height > 0) return width / height;
    return fallback;
  }

  const value = Number.parseFloat(text);
  return value > 0 ? value : fallback;
}

/** Output pixel size for a crop of the given aspect ratio, capped to sane bounds. */
export function outputSize({ aspect, width, maxPixels = 40e6, maxSide = 12000 }) {
  const ratio = Number.isFinite(aspect) && aspect > 0 ? aspect : 1;
  let outWidth = Math.max(1, Math.round(width || 1));
  let outHeight = Math.max(1, Math.round(outWidth / ratio));

  const shrinkToSide = Math.min(1, maxSide / Math.max(outWidth, outHeight));
  const shrinkToArea = Math.min(1, Math.sqrt(maxPixels / (outWidth * outHeight)));
  const shrink = Math.min(shrinkToSide, shrinkToArea);
  if (shrink < 1) {
    outWidth = Math.max(1, Math.round(outWidth * shrink));
    outHeight = Math.max(1, Math.round(outHeight * shrink));
  }

  return { width: outWidth, height: outHeight };
}

/**
 * Normalised crop rectangle (0..1 of the source image) for the current
 * transform. Useful for storing a crop server-side or re-cropping the original
 * later. With rotation the rectangle describes the frame's bounding box.
 */
export function normalizedCropRect({
  imageWidth,
  imageHeight,
  cropWidth,
  cropHeight,
  rotation = 0,
  scale = 1,
  offsetX = 0,
  offsetY = 0,
}) {
  const { halfWidth, halfHeight } = rotatedHalfExtent(cropWidth / 2, cropHeight / 2, rotation);
  const local = rotatePoint(offsetX, offsetY, -rotation);
  const centreX = imageWidth / 2 - local.x / scale;
  const centreY = imageHeight / 2 - local.y / scale;
  const width = (halfWidth * 2) / scale;
  const height = (halfHeight * 2) / scale;

  return {
    x: (centreX - width / 2) / imageWidth,
    y: (centreY - height / 2) / imageHeight,
    width: width / imageWidth,
    height: height / imageHeight,
  };
}

export function formatBytes(bytes) {
  if (!Number.isFinite(bytes) || bytes < 0) return '—';
  if (bytes < 1024) return `${bytes} B`;
  const units = ['KB', 'MB', 'GB'];
  let value = bytes / 1024;
  let unitIndex = 0;
  while (value >= 1024 && unitIndex < units.length - 1) {
    value /= 1024;
    unitIndex += 1;
  }
  return `${value < 10 ? value.toFixed(1) : Math.round(value)} ${units[unitIndex]}`;
}
