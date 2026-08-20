/**
 * ImageCropper — a dependency-free crop stage built on a 2D canvas.
 *
 * Responsibilities: paint the image under the crop frame, translate mouse,
 * touch, wheel and keyboard input into transform changes, and render the final
 * crop at an arbitrary output size.
 *
 * The instance owns no application state beyond the current transform, so a
 * host can snapshot it per image (see `getSnapshot` / `applySnapshot`).
 */

import {
  DEG_TO_RAD,
  clamp,
  clampOffset,
  containScale,
  fitBox,
  minimumCoverScale,
  normalizeAngle,
  normalizedCropRect,
  offsetForRotationAroundCentre,
  offsetForScaleAroundAnchor,
  outputSize,
  panLimits,
  rotatePoint,
} from './geometry.js';
import {
  DEFAULT_ADJUSTMENTS,
  applyAdjustmentsToPixels,
  isNeutralAdjustments,
  normalizeAdjustments,
  toCssFilter,
} from './filters.js';

export const MAX_ZOOM = 10;
export const MIN_ZOOM = 1;
export const FRAME_PADDING = 28;

const KEYBOARD_PAN_STEP = 12;
const KEYBOARD_ZOOM_STEP = 1.08;
const KEYBOARD_ROTATE_STEP = 1;

export const TRANSPARENT = 'transparent';

function createDefaultState() {
  return {
    zoom: 1,
    rotation: 0,
    panX: 0,
    panY: 0,
    flipH: false,
    flipV: false,
    aspect: 1,
    /** `cover` never leaves gaps; `contain` allows padding around the image. */
    fit: 'cover',
    background: TRANSPARENT,
    adjustments: { ...DEFAULT_ADJUSTMENTS },
  };
}

export class ImageCropper {
  #container;
  #canvas;
  #context;
  #frameElement;
  #backdropElement;
  #listeners = new Map();
  #pointers = new Map();
  #gesture = null;
  #resizeObserver;
  #frameRequest = 0;
  #viewport = { width: 0, height: 0 };
  #frame = { x: 0, y: 0, width: 0, height: 0 };
  #image = null;
  #imageMeta = null;
  #state = createDefaultState();
  #showGrid = true;
  #canvasId = '';

  constructor(container, { aspect = 1, showGrid = true, canvasId = '' } = {}) {
    if (!container) throw new Error('ImageCropper requires a container element');
    this.#container = container;
    this.#state.aspect = aspect > 0 ? aspect : 1;
    this.#showGrid = showGrid;
    this.#canvasId = canvasId;

    this.#buildDom();
    this.#bindEvents();
    this.#measure();
  }

  #buildDom() {
    const doc = this.#container.ownerDocument;
    this.#container.classList.add('cropper');
    this.#container.innerHTML = '';

    // Sits under the transparent canvas so the padding colour of "contain" mode
    // is not affected by the CSS filter used for the live preview.
    this.#backdropElement = doc.createElement('div');
    this.#backdropElement.className = 'cropper__backdrop';
    this.#backdropElement.setAttribute('aria-hidden', 'true');

    this.#canvas = doc.createElement('canvas');
    this.#canvas.className = 'cropper__canvas';
    if (this.#canvasId) this.#canvas.id = this.#canvasId;
    this.#canvas.tabIndex = 0;
    this.#canvas.setAttribute('role', 'application');
    this.#canvas.setAttribute('aria-keyshortcuts', 'ArrowUp ArrowDown ArrowLeft ArrowRight + - [ ] 0');
    this.#canvas.setAttribute(
      'aria-label',
      'אזור החיתוך. גרירה עם העכבר מזיזה את התמונה, גלגלת מקרבת ומרחיקה. '
        + 'עם המקלדת: מקשי חצים להזזה, פלוס ומינוס לזום, סוגריים מרובעים לסיבוב, אפס לאיפוס.',
    );
    this.#context = this.#canvas.getContext('2d');

    this.#frameElement = doc.createElement('div');
    this.#frameElement.className = 'cropper__frame';
    this.#frameElement.setAttribute('aria-hidden', 'true');
    this.#frameElement.innerHTML = `
      <div class="cropper__grid"></div>
      <span class="cropper__handle cropper__handle--tl"></span>
      <span class="cropper__handle cropper__handle--tr"></span>
      <span class="cropper__handle cropper__handle--bl"></span>
      <span class="cropper__handle cropper__handle--br"></span>
    `;

    this.#container.append(this.#backdropElement, this.#canvas, this.#frameElement);
    this.setGridVisible(this.#showGrid);
  }

  #bindEvents() {
    const canvas = this.#canvas;
    canvas.addEventListener('pointerdown', this.#onPointerDown);
    canvas.addEventListener('pointermove', this.#onPointerMove);
    canvas.addEventListener('pointerup', this.#onPointerUp);
    canvas.addEventListener('pointercancel', this.#onPointerUp);
    canvas.addEventListener('lostpointercapture', this.#onPointerUp);
    canvas.addEventListener('wheel', this.#onWheel, { passive: false });
    canvas.addEventListener('dblclick', this.#onDoubleClick);
    canvas.addEventListener('keydown', this.#onKeyDown);

    if (typeof ResizeObserver === 'function') {
      this.#resizeObserver = new ResizeObserver(() => this.#measure());
      this.#resizeObserver.observe(this.#container);
    } else {
      this.#container.ownerDocument.defaultView?.addEventListener('resize', this.#measure);
    }
  }

  destroy() {
    this.#resizeObserver?.disconnect();
    this.#container.ownerDocument.defaultView?.removeEventListener('resize', this.#measure);
    if (this.#frameRequest) cancelAnimationFrame(this.#frameRequest);
    this.#listeners.clear();
    this.#container.innerHTML = '';
  }

  /* ---------------------------------------------------------------- events */

  on(event, handler) {
    if (!this.#listeners.has(event)) this.#listeners.set(event, new Set());
    this.#listeners.get(event).add(handler);
    return () => this.#listeners.get(event)?.delete(handler);
  }

  #emit(event, detail) {
    for (const handler of this.#listeners.get(event) ?? []) handler(detail);
  }

  /* ----------------------------------------------------------- image input */

  get hasImage() {
    return Boolean(this.#image);
  }

  get imageMeta() {
    return this.#imageMeta ? { ...this.#imageMeta } : null;
  }

  /**
   * Loads a `File`, `Blob`, URL string, `ImageBitmap` or `HTMLImageElement`.
   * Resets the transform unless `keepTransform` is set.
   */
  async setImage(source, { keepTransform = false } = {}) {
    const { image, width, height } = await loadImageSource(source);
    this.#image = image;
    this.#imageMeta = {
      width,
      height,
      name: typeof source === 'object' && source && 'name' in source ? source.name : undefined,
    };

    if (!keepTransform) {
      const { aspect, fit, background } = this.#state;
      this.#state = { ...createDefaultState(), aspect, fit, background };
    }
    this.#measure();
    this.#clampState();
    this.#render();
    this.#emit('load', this.imageMeta);
    this.#emit('change', this.getState());
    return this.imageMeta;
  }

  clearImage() {
    this.#image = null;
    this.#imageMeta = null;
    const { aspect, fit, background } = this.#state;
    this.#state = { ...createDefaultState(), aspect, fit, background };
    this.#render();
    this.#emit('change', this.getState());
  }

  /* ------------------------------------------------------------- geometry  */

  #measure = () => {
    const rect = this.#container.getBoundingClientRect();
    const width = Math.max(0, Math.round(rect.width));
    const height = Math.max(0, Math.round(rect.height));
    this.#viewport = { width, height };

    const ratio = Math.min(this.#container.ownerDocument.defaultView?.devicePixelRatio || 1, 3);
    const pixelWidth = Math.max(1, Math.round(width * ratio));
    const pixelHeight = Math.max(1, Math.round(height * ratio));
    if (this.#canvas.width !== pixelWidth || this.#canvas.height !== pixelHeight) {
      this.#canvas.width = pixelWidth;
      this.#canvas.height = pixelHeight;
    }

    this.#frame = fitBox({
      containerWidth: width,
      containerHeight: height,
      aspect: this.#state.aspect,
      padding: Math.min(FRAME_PADDING, Math.min(width, height) * 0.08),
    });

    for (const element of [this.#frameElement, this.#backdropElement]) {
      element.style.width = `${this.#frame.width}px`;
      element.style.height = `${this.#frame.height}px`;
      element.style.left = `${this.#frame.x}px`;
      element.style.top = `${this.#frame.y}px`;
    }

    this.#clampState();
    this.#render();
  };

  get frame() {
    return { ...this.#frame };
  }

  /** Scale (image pixels → screen pixels) at which the image just covers the frame. */
  get minScale() {
    if (!this.#image) return 1;
    return minimumCoverScale({
      imageWidth: this.#imageMeta.width,
      imageHeight: this.#imageMeta.height,
      cropWidth: this.#frame.width,
      cropHeight: this.#frame.height,
      rotation: this.#state.rotation,
    });
  }

  get scale() {
    return this.minScale * this.#state.zoom;
  }

  /**
   * Lowest zoom the user may reach. In `contain` mode this goes below 1 (the
   * cover scale) so the whole image fits with padding around it.
   */
  get minZoom() {
    if (this.#state.fit !== 'contain' || !this.#image) return MIN_ZOOM;
    const contain = containScale({
      imageWidth: this.#imageMeta.width,
      imageHeight: this.#imageMeta.height,
      cropWidth: this.#frame.width,
      cropHeight: this.#frame.height,
      rotation: this.#state.rotation,
    });
    const minScale = this.minScale;
    if (!(minScale > 0) || !(contain > 0)) return MIN_ZOOM;
    return Math.min(MIN_ZOOM, contain / minScale);
  }

  #offsetPixels() {
    return {
      x: this.#state.panX * this.#frame.width,
      y: this.#state.panY * this.#frame.width,
    };
  }

  #setOffsetPixels(x, y) {
    const width = this.#frame.width || 1;
    this.#state.panX = x / width;
    this.#state.panY = y / width;
  }

  #clampState() {
    const state = this.#state;
    state.rotation = normalizeAngle(state.rotation);
    state.zoom = clamp(state.zoom, this.minZoom, MAX_ZOOM);
    state.adjustments = normalizeAdjustments(state.adjustments);
    if (!this.#image) return;

    const offset = this.#offsetPixels();
    const clamped = clampOffset({
      imageWidth: this.#imageMeta.width,
      imageHeight: this.#imageMeta.height,
      cropWidth: this.#frame.width,
      cropHeight: this.#frame.height,
      rotation: state.rotation,
      scale: this.scale,
      offsetX: offset.x,
      offsetY: offset.y,
    });
    this.#setOffsetPixels(clamped.offsetX, clamped.offsetY);
  }

  /* -------------------------------------------------------------- painting */

  #render() {
    if (this.#frameRequest) return;
    const view = this.#container.ownerDocument.defaultView;
    const schedule = view?.requestAnimationFrame?.bind(view) ?? ((cb) => setTimeout(cb, 16));
    this.#frameRequest = schedule(() => {
      this.#frameRequest = 0;
      this.#paint();
    });
  }

  #paint() {
    const ctx = this.#context;
    if (!ctx) return;

    const ratio = this.#canvas.width / (this.#viewport.width || 1);
    ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
    ctx.clearRect(0, 0, this.#viewport.width, this.#viewport.height);

    this.#container.classList.toggle('cropper--empty', !this.#image);
    this.#canvas.style.filter = toCssFilter(this.#state.adjustments);

    const transparent = this.#state.background === TRANSPARENT;
    this.#backdropElement.classList.toggle('cropper__backdrop--checker', transparent);
    this.#backdropElement.style.backgroundColor = transparent ? '' : this.#state.background;

    if (!this.#image) return;

    const { rotation, flipH, flipV } = this.#state;
    const offset = this.#offsetPixels();
    const scale = this.scale;

    ctx.save();
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.translate(
      this.#frame.x + this.#frame.width / 2 + offset.x,
      this.#frame.y + this.#frame.height / 2 + offset.y,
    );
    ctx.rotate(rotation * DEG_TO_RAD);
    ctx.scale(scale * (flipH ? -1 : 1), scale * (flipV ? -1 : 1));
    ctx.drawImage(
      this.#image,
      -this.#imageMeta.width / 2,
      -this.#imageMeta.height / 2,
      this.#imageMeta.width,
      this.#imageMeta.height,
    );
    ctx.restore();
  }

  /* ------------------------------------------------------------ public API */

  getState() {
    const state = this.#state;
    return {
      zoom: state.zoom,
      rotation: state.rotation,
      panX: state.panX,
      panY: state.panY,
      flipH: state.flipH,
      flipV: state.flipV,
      aspect: state.aspect,
      fit: state.fit,
      background: state.background,
      adjustments: { ...state.adjustments },
      scale: this.scale,
      minScale: this.minScale,
      minZoom: this.minZoom,
      panRatio: this.getPanRatio(),
      panAvailable: this.getPanAvailable(),
      cropRect: this.getCropRect(),
      frame: this.frame,
      image: this.imageMeta,
    };
  }

  getSnapshot() {
    const state = this.#state;
    return {
      zoom: state.zoom,
      rotation: state.rotation,
      panX: state.panX,
      panY: state.panY,
      flipH: state.flipH,
      flipV: state.flipV,
      aspect: state.aspect,
      fit: state.fit,
      background: state.background,
      adjustments: { ...state.adjustments },
    };
  }

  applySnapshot(snapshot = {}, { silent = false } = {}) {
    const base = createDefaultState();
    this.#state = {
      ...base,
      ...snapshot,
      adjustments: normalizeAdjustments({ ...base.adjustments, ...(snapshot.adjustments ?? {}) }),
    };
    this.#measure();
    if (!silent) this.#commit();
  }

  #commit() {
    this.#clampState();
    this.#render();
    this.#emit('change', this.getState());
  }

  setAspect(aspect) {
    const value = Number(aspect);
    this.#state.aspect = value > 0 ? value : 1;
    this.#measure();
    this.#commit();
  }

  setZoom(zoom, anchor = null) {
    const next = clamp(Number(zoom), this.minZoom, MAX_ZOOM);
    const previousScale = this.scale;
    this.#state.zoom = next;
    const nextScale = this.scale;

    if (anchor && previousScale > 0) {
      const offset = this.#offsetPixels();
      const moved = offsetForScaleAroundAnchor({
        offsetX: offset.x,
        offsetY: offset.y,
        fromScale: previousScale,
        toScale: nextScale,
        anchorX: anchor.x,
        anchorY: anchor.y,
      });
      this.#setOffsetPixels(moved.offsetX, moved.offsetY);
    }
    this.#commit();
  }

  zoomBy(factor, anchor = null) {
    this.setZoom(this.#state.zoom * factor, anchor);
  }

  setRotation(degrees) {
    const next = normalizeAngle(Number(degrees) || 0);
    const delta = next - this.#state.rotation;
    this.#state.rotation = next;
    const offset = this.#offsetPixels();
    const moved = offsetForRotationAroundCentre({
      offsetX: offset.x,
      offsetY: offset.y,
      deltaDegrees: delta,
    });
    this.#setOffsetPixels(moved.offsetX, moved.offsetY);
    this.#commit();
  }

  rotateBy(degrees) {
    this.setRotation(this.#state.rotation + degrees);
  }

  setFlip({ horizontal, vertical } = {}) {
    if (typeof horizontal === 'boolean') this.#state.flipH = horizontal;
    if (typeof vertical === 'boolean') this.#state.flipV = vertical;
    this.#commit();
  }

  toggleFlip(axis) {
    if (axis === 'vertical') this.#state.flipV = !this.#state.flipV;
    else this.#state.flipH = !this.#state.flipH;
    this.#commit();
  }

  setAdjustment(key, value) {
    this.#state.adjustments = normalizeAdjustments({
      ...this.#state.adjustments,
      [key]: Number(value),
    });
    this.#commit();
  }

  resetAdjustments() {
    this.#state.adjustments = { ...DEFAULT_ADJUSTMENTS };
    this.#commit();
  }

  /** Pan freedom in image-space pixels on each axis (0 when the axis is locked). */
  getPanAvailable() {
    if (!this.#image) return { x: 0, y: 0 };
    const limits = panLimits({
      imageWidth: this.#imageMeta.width,
      imageHeight: this.#imageMeta.height,
      cropWidth: this.#frame.width,
      cropHeight: this.#frame.height,
      rotation: this.#state.rotation,
      scale: this.scale,
    });
    return { x: Math.max(0, limits.x), y: Math.max(0, limits.y) };
  }

  /** Pan position as -1..1 on each axis of the (rotated) image. */
  getPanRatio() {
    const available = this.getPanAvailable();
    const offset = this.#offsetPixels();
    const local = rotatePoint(offset.x, offset.y, -this.#state.rotation);
    return {
      x: available.x > 0.001 ? clamp(local.x / available.x, -1, 1) : 0,
      y: available.y > 0.001 ? clamp(local.y / available.y, -1, 1) : 0,
    };
  }

  setPanRatio({ x, y } = {}) {
    const available = this.getPanAvailable();
    const current = this.getPanRatio();
    const nextX = Number.isFinite(x) ? clamp(x, -1, 1) : current.x;
    const nextY = Number.isFinite(y) ? clamp(y, -1, 1) : current.y;
    const screen = rotatePoint(nextX * available.x, nextY * available.y, this.#state.rotation);
    this.#setOffsetPixels(screen.x, screen.y);
    this.#commit();
  }

  panByPixels(dx, dy) {
    const offset = this.#offsetPixels();
    this.#setOffsetPixels(offset.x + dx, offset.y + dy);
    this.#commit();
  }

  setGridVisible(visible) {
    this.#showGrid = Boolean(visible);
    this.#container.classList.toggle('cropper--grid', this.#showGrid);
  }

  get gridVisible() {
    return this.#showGrid;
  }

  /** Centres the image and returns to the tightest zoom, keeping output settings. */
  reset() {
    const { aspect, fit, background } = this.#state;
    this.#state = { ...createDefaultState(), aspect, fit, background };
    this.#state.zoom = this.minZoom;
    this.#commit();
  }

  /** `cover` fills the frame edge to edge, `contain` shows the whole image. */
  setFitMode(mode) {
    this.#state.fit = mode === 'contain' ? 'contain' : 'cover';
    if (this.#state.fit === 'contain') this.fitWholeImage();
    else this.#commit();
  }

  setBackground(color) {
    this.#state.background = color || TRANSPARENT;
    this.#commit();
  }

  /** Zooms out until the whole image is inside the frame, padding included. */
  fitWholeImage() {
    if (!this.#image) return;
    this.#state.fit = 'contain';
    this.#state.panX = 0;
    this.#state.panY = 0;
    this.#state.zoom = this.minZoom;
    this.#commit();
  }

  /** Fills the frame with the tightest zoom that leaves no gaps. */
  fillFrame() {
    this.#state.fit = 'cover';
    this.#state.zoom = MIN_ZOOM;
    this.#state.panX = 0;
    this.#state.panY = 0;
    this.#commit();
  }

  getCropRect() {
    if (!this.#image) return null;
    const offset = this.#offsetPixels();
    return normalizedCropRect({
      imageWidth: this.#imageMeta.width,
      imageHeight: this.#imageMeta.height,
      cropWidth: this.#frame.width,
      cropHeight: this.#frame.height,
      rotation: this.#state.rotation,
      scale: this.scale,
      offsetX: offset.x,
      offsetY: offset.y,
    });
  }

  /** Native pixel width of the crop at the current zoom (the "1:1" size). */
  getNativeCropWidth() {
    if (!this.#image) return 0;
    return Math.round(this.#frame.width / this.scale);
  }

  /* ---------------------------------------------------------------- export */

  /** Renders the crop into a detached canvas at the requested output size. */
  renderToCanvas({ width, background } = {}) {
    if (!this.#image) throw new Error('אין תמונה לחיתוך');
    const fill = background === undefined
      ? (this.#state.background === TRANSPARENT ? null : this.#state.background)
      : background;
    const target = outputSize({
      aspect: this.#state.aspect,
      width: width || this.getNativeCropWidth() || this.#frame.width,
    });

    const doc = this.#container.ownerDocument;
    const canvas = doc.createElement('canvas');
    canvas.width = target.width;
    canvas.height = target.height;
    const ctx = canvas.getContext('2d');

    if (fill) {
      ctx.fillStyle = fill;
      ctx.fillRect(0, 0, target.width, target.height);
    }

    const filter = toCssFilter(this.#state.adjustments);
    const supportsContextFilter = 'filter' in ctx;
    if (supportsContextFilter && filter !== 'none') ctx.filter = filter;

    const factor = target.width / this.#frame.width;
    const offset = this.#offsetPixels();
    const scale = this.scale * factor;

    ctx.save();
    ctx.imageSmoothingEnabled = true;
    ctx.imageSmoothingQuality = 'high';
    ctx.translate(target.width / 2 + offset.x * factor, target.height / 2 + offset.y * factor);
    ctx.rotate(this.#state.rotation * DEG_TO_RAD);
    ctx.scale(scale * (this.#state.flipH ? -1 : 1), scale * (this.#state.flipV ? -1 : 1));
    ctx.drawImage(
      this.#image,
      -this.#imageMeta.width / 2,
      -this.#imageMeta.height / 2,
      this.#imageMeta.width,
      this.#imageMeta.height,
    );
    ctx.restore();
    if (supportsContextFilter) ctx.filter = 'none';

    if (!supportsContextFilter && !isNeutralAdjustments(this.#state.adjustments)) {
      const pixels = ctx.getImageData(0, 0, target.width, target.height);
      applyAdjustmentsToPixels(pixels, this.#state.adjustments);
      ctx.putImageData(pixels, 0, 0);
    }

    return canvas;
  }

  /**
   * Exports the crop as a Blob.
   * JPEG gets an opaque background because it has no alpha channel.
   */
  async toBlob({ width, format = 'image/jpeg', quality = 0.9, background } = {}) {
    const opaque = format === 'image/jpeg';
    const stateBackground = this.#state.background === TRANSPARENT ? null : this.#state.background;
    const fill = background ?? stateBackground ?? (opaque ? '#ffffff' : null);
    const canvas = this.renderToCanvas({ width, background: fill });
    const blob = await canvasToBlob(canvas, format, quality);
    return {
      blob,
      width: canvas.width,
      height: canvas.height,
      format: blob.type || format,
      requestedFormat: format,
    };
  }

  /* ----------------------------------------------------------- interaction */

  #pointFromEvent(event) {
    const rect = this.#canvas.getBoundingClientRect();
    return {
      x: event.clientX - rect.left - (this.#frame.x + this.#frame.width / 2),
      y: event.clientY - rect.top - (this.#frame.y + this.#frame.height / 2),
    };
  }

  #onPointerDown = (event) => {
    if (!this.#image || event.button !== 0) return;
    this.#canvas.setPointerCapture?.(event.pointerId);
    this.#pointers.set(event.pointerId, this.#pointFromEvent(event));
    this.#container.classList.add('cropper--dragging');

    if (this.#pointers.size === 2) this.#gesture = this.#createGesture();
    // preventDefault stops text selection while dragging, but it also cancels
    // the default focus, so focus explicitly: keyboard control has to keep
    // working right after a click.
    event.preventDefault();
    this.#canvas.focus({ preventScroll: true });
  };

  #onPointerMove = (event) => {
    if (!this.#pointers.has(event.pointerId)) return;
    const point = this.#pointFromEvent(event);
    const previous = this.#pointers.get(event.pointerId);
    this.#pointers.set(event.pointerId, point);

    if (this.#pointers.size >= 2) {
      this.#applyGesture();
    } else {
      const offset = this.#offsetPixels();
      this.#setOffsetPixels(offset.x + (point.x - previous.x), offset.y + (point.y - previous.y));
      this.#commit();
    }
    event.preventDefault();
  };

  #onPointerUp = (event) => {
    this.#pointers.delete(event.pointerId);
    if (this.#canvas.hasPointerCapture?.(event.pointerId)) {
      this.#canvas.releasePointerCapture(event.pointerId);
    }
    // Re-baseline so lifting one finger of a pinch does not jump the image.
    this.#gesture = this.#pointers.size >= 2 ? this.#createGesture() : null;
    if (this.#pointers.size === 0) this.#container.classList.remove('cropper--dragging');
  };

  #createGesture() {
    const [first, second] = [...this.#pointers.values()];
    if (!first || !second) return null;
    return {
      distance: Math.hypot(second.x - first.x, second.y - first.y),
      angle: Math.atan2(second.y - first.y, second.x - first.x) / DEG_TO_RAD,
      zoom: this.#state.zoom,
      rotation: this.#state.rotation,
    };
  }

  #applyGesture() {
    const gesture = this.#gesture;
    if (!gesture) {
      this.#gesture = this.#createGesture();
      return;
    }
    const [first, second] = [...this.#pointers.values()];
    const distance = Math.hypot(second.x - first.x, second.y - first.y);
    const angle = Math.atan2(second.y - first.y, second.x - first.x) / DEG_TO_RAD;
    const midpoint = { x: (first.x + second.x) / 2, y: (first.y + second.y) / 2 };

    if (gesture.distance > 8 && distance > 8) {
      this.setZoom((gesture.zoom * distance) / gesture.distance, midpoint);
    }
    const rotationDelta = normalizeAngle(angle - gesture.angle);
    if (Math.abs(rotationDelta) > 2) this.setRotation(gesture.rotation + rotationDelta);
  }

  #onWheel = (event) => {
    if (!this.#image) return;
    event.preventDefault();
    const unit = event.deltaMode === 1 ? 16 : event.deltaMode === 2 ? 100 : 1;
    const delta = event.deltaY * unit;
    const factor = Math.exp(-delta * (event.ctrlKey ? 0.008 : 0.0022));
    this.zoomBy(factor, this.#pointFromEvent(event));
  };

  #onDoubleClick = (event) => {
    if (!this.#image) return;
    event.preventDefault();
    if (this.#state.zoom > this.minZoom * 1.02) this.setZoom(this.minZoom);
    else this.setZoom(this.minZoom * 2, this.#pointFromEvent(event));
  };

  #onKeyDown = (event) => {
    if (!this.#image) return;
    const step = event.shiftKey ? KEYBOARD_PAN_STEP * 4 : KEYBOARD_PAN_STEP;
    const handlers = {
      ArrowLeft: () => this.panByPixels(-step, 0),
      ArrowRight: () => this.panByPixels(step, 0),
      ArrowUp: () => this.panByPixels(0, -step),
      ArrowDown: () => this.panByPixels(0, step),
      '+': () => this.zoomBy(KEYBOARD_ZOOM_STEP),
      '=': () => this.zoomBy(KEYBOARD_ZOOM_STEP),
      '-': () => this.zoomBy(1 / KEYBOARD_ZOOM_STEP),
      _: () => this.zoomBy(1 / KEYBOARD_ZOOM_STEP),
      '[': () => this.rotateBy(-(event.shiftKey ? 15 : KEYBOARD_ROTATE_STEP)),
      ']': () => this.rotateBy(event.shiftKey ? 15 : KEYBOARD_ROTATE_STEP),
      0: () => this.reset(),
    };

    const handler = handlers[event.key];
    if (!handler) return;
    event.preventDefault();
    handler();
  };
}

/* ------------------------------------------------------------------ helpers */

export function canvasToBlob(canvas, format, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error('יצירת הקובץ נכשלה'))),
      format,
      quality,
    );
  });
}

/** Normalises every supported image source into something `drawImage` accepts. */
export async function loadImageSource(source) {
  if (!source) throw new Error('לא סופקה תמונה');

  if (typeof ImageBitmap !== 'undefined' && source instanceof ImageBitmap) {
    return { image: source, width: source.width, height: source.height };
  }

  if (typeof HTMLImageElement !== 'undefined' && source instanceof HTMLImageElement) {
    await source.decode?.().catch(() => {});
    return { image: source, width: source.naturalWidth, height: source.naturalHeight };
  }

  if (typeof Blob !== 'undefined' && source instanceof Blob) {
    if (typeof createImageBitmap === 'function') {
      try {
        // `from-image` honours the EXIF orientation of photos from phones.
        const bitmap = await createImageBitmap(source, { imageOrientation: 'from-image' });
        return { image: bitmap, width: bitmap.width, height: bitmap.height };
      } catch {
        /* falls through to the <img> path */
      }
    }
    const url = URL.createObjectURL(source);
    try {
      return await loadImageElement(url);
    } finally {
      // Revoked on the next tick so the decoded element keeps its pixels.
      setTimeout(() => URL.revokeObjectURL(url), 0);
    }
  }

  if (typeof source === 'string') return loadImageElement(source);

  throw new Error('סוג התמונה אינו נתמך');
}

function loadImageElement(src) {
  return new Promise((resolve, reject) => {
    const image = new Image();
    image.crossOrigin = 'anonymous';
    image.decoding = 'async';
    image.onload = () => resolve({ image, width: image.naturalWidth, height: image.naturalHeight });
    image.onerror = () => reject(new Error('טעינת התמונה נכשלה'));
    image.src = src;
  });
}
