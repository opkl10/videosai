/**
 * Studio shell: file queue, controls, export and the library of saved images.
 * All cropping logic lives in `cropper.js`; this file only wires UI to it.
 */

import { ImageCropper, TRANSPARENT } from './cropper.js';
import { ADJUSTMENTS } from './filters.js';
import { formatBytes, parseAspect } from './geometry.js';
import * as api from './api.js';

const ASPECT_PRESETS = [
  { id: 'original', label: 'מקורי', ratio: null },
  { id: 'square', label: '1:1', ratio: 1 },
  { id: 'portrait45', label: '4:5', ratio: 4 / 5 },
  { id: 'portrait34', label: '3:4', ratio: 3 / 4 },
  { id: 'story', label: '9:16', ratio: 9 / 16 },
  { id: 'classic43', label: '4:3', ratio: 4 / 3 },
  { id: 'photo32', label: '3:2', ratio: 3 / 2 },
  { id: 'wide169', label: '16:9', ratio: 16 / 9 },
  { id: 'cinema', label: '21:9', ratio: 21 / 9 },
  { id: 'banner', label: '3:1', ratio: 3 },
];

/** Ready made output targets for common slots on the site. */
const SITE_TARGETS = [
  { id: 'card', label: 'כרטיס תמונה — 800×600', width: 800, ratio: 4 / 3 },
  { id: 'hero', label: 'באנר ראשי — 1920×640', width: 1920, ratio: 3 },
  { id: 'social', label: 'שיתוף ברשתות — 1200×630', width: 1200, ratio: 1200 / 630 },
  { id: 'avatar', label: 'תמונת פרופיל — 512×512', width: 512, ratio: 1 },
  { id: 'story', label: 'סטורי — 1080×1920', width: 1080, ratio: 9 / 16 },
  { id: 'thumb', label: 'תמונה קטנה — 400×400', width: 400, ratio: 1 },
];

const FORMAT_EXTENSIONS = {
  'image/jpeg': 'jpg',
  'image/webp': 'webp',
  'image/png': 'png',
};

const MAX_UPLOAD_BYTES_FALLBACK = 25 * 1024 * 1024;

const element = (id) => document.getElementById(id);

const ui = {
  connectionStatus: element('connection-status'),
  helpButton: element('help-button'),
  themeButton: element('theme-button'),
  helpDialog: element('help-dialog'),
  tokenDialog: element('token-dialog'),
  tokenForm: element('token-form'),
  tokenInput: element('token-input'),
  tokenCancel: element('token-cancel'),
  dropzone: element('dropzone'),
  fileInput: element('file-input'),
  fileList: element('file-list'),
  fileListEmpty: element('file-list-empty'),
  sizeLimitHint: element('size-limit-hint'),
  stage: element('stage'),
  cropperHost: element('cropper-host'),
  stageMeta: element('stage-meta'),
  aspectPresets: element('aspect-presets'),
  ratioWidth: element('ratio-width'),
  ratioHeight: element('ratio-height'),
  ratioApply: element('ratio-apply'),
  targetPreset: element('target-preset'),
  zoomSlider: element('zoom-slider'),
  zoomOutput: element('zoom-output'),
  rotationSlider: element('rotation-slider'),
  rotationOutput: element('rotation-output'),
  panXSlider: element('pan-x-slider'),
  panXOutput: element('pan-x-output'),
  panYSlider: element('pan-y-slider'),
  panYOutput: element('pan-y-output'),
  adjustmentControls: element('adjustment-controls'),
  resetAdjustments: element('reset-adjustments'),
  backgroundColor: element('background-color'),
  backgroundTransparent: element('background-transparent'),
  outputWidth: element('output-width'),
  outputWidthSlider: element('output-width-slider'),
  outputSizeOutput: element('output-size-output'),
  outputNative: element('output-native'),
  outputFormat: element('output-format'),
  qualityControl: element('quality-control'),
  qualitySlider: element('quality-slider'),
  qualityOutput: element('quality-output'),
  outputName: element('output-name'),
  outputEstimate: element('output-estimate'),
  saveButton: element('save-button'),
  downloadButton: element('download-button'),
  applyAllButton: element('apply-all-button'),
  saveAllButton: element('save-all-button'),
  library: element('library'),
  libraryEmpty: element('library-empty'),
  refreshLibrary: element('refresh-library'),
  toast: element('toast'),
  liveRegion: element('live-region'),
};

const state = {
  files: [],
  activeId: null,
  activePreset: 'original',
  serverAvailable: false,
  requiresToken: false,
  maxUploadBytes: MAX_UPLOAD_BYTES_FALLBACK,
  syncing: false,
  busy: false,
};

const cropper = new ImageCropper(ui.cropperHost, {
  aspect: 1,
  showGrid: true,
  canvasId: 'crop-canvas',
});

/* ------------------------------------------------------------------ helpers */

let toastTimer = 0;
function toast(message, kind = 'info') {
  ui.toast.textContent = message;
  ui.toast.dataset.state = kind;
  ui.toast.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    ui.toast.hidden = true;
  }, 4200);
}

function announce(message) {
  ui.liveRegion.textContent = message;
}

function activeFile() {
  return state.files.find((file) => file.id === state.activeId) ?? null;
}

function paintRange(input) {
  const min = Number(input.min);
  const max = Number(input.max);
  const value = Number(input.value);
  const fill = max > min ? ((value - min) / (max - min)) * 100 : 0;
  input.style.setProperty('--fill', `${fill}%`);
}

/** Avoids fighting a slider the user is currently dragging. */
function setSliderValue(input, value) {
  if (document.activeElement !== input) input.value = String(value);
  paintRange(input);
}

function fileStem(name) {
  return String(name ?? '')
    .replace(/\.[^.]+$/u, '')
    .slice(0, 70);
}

/**
 * Wraps a value in Unicode isolate marks (FSI…PDI).
 *
 * Without this, Latin text and digits mixed into a Hebrew sentence are
 * reordered by the bidi algorithm — "14 KB · 1200×1500 · WEBP" would render as
 * "KB 1 · 1200×1,500 · WEBP 14". Isolation keeps every value readable while the
 * sentence itself stays right to left.
 */
function isolate(value) {
  return `\u2068${value}\u2069`;
}

/** Plain digits, no thousands separator: a comma inside an isolate still splits. */
function dimensions(width, height) {
  return isolate(`${Math.round(width)}×${Math.round(height)}`);
}

function formatNumber(value) {
  return isolate(Number(value).toLocaleString('he-IL'));
}

/* -------------------------------------------------------------------- theme */

function initTheme() {
  const stored = localStorage.getItem('image-studio-theme');
  const theme = stored ?? (window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark');
  applyTheme(theme);
  ui.themeButton.addEventListener('click', () => {
    applyTheme(document.documentElement.dataset.theme === 'light' ? 'dark' : 'light');
  });
}

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  ui.themeButton.textContent = theme === 'light' ? 'מצב כהה' : 'מצב בהיר';
  ui.themeButton.setAttribute('aria-pressed', String(theme === 'light'));
  localStorage.setItem('image-studio-theme', theme);
}

/* --------------------------------------------------------------- file queue */

let idCounter = 0;

function addFiles(fileLikeList) {
  const incoming = [...fileLikeList].filter((file) => file && file.type?.startsWith('image/'));
  if (!incoming.length) {
    toast('לא נמצאו קבצי תמונה בבחירה', 'error');
    return;
  }

  const accepted = [];
  for (const file of incoming) {
    if (file.size > state.maxUploadBytes) {
      toast(
        `הקובץ ${isolate(file.name)} גדול מהמותר (${isolate(formatBytes(state.maxUploadBytes))})`,
        'error',
      );
      continue;
    }
    idCounter += 1;
    accepted.push({
      id: `file-${idCounter}`,
      file,
      name: file.name || `תמונה-${idCounter}`,
      size: file.size,
      previewUrl: URL.createObjectURL(file),
      snapshot: null,
      savedName: null,
    });
  }

  if (!accepted.length) return;
  state.files.push(...accepted);
  renderFileList();
  announce(
    accepted.length === 1
      ? `נוספה התמונה ${accepted[0].name}`
      : `נוספו ${accepted.length} תמונות לעריכה`,
  );
  if (!state.activeId) selectFile(accepted[0].id);
}

function removeFile(id) {
  const index = state.files.findIndex((file) => file.id === id);
  if (index === -1) return;
  URL.revokeObjectURL(state.files[index].previewUrl);
  state.files.splice(index, 1);

  if (state.activeId === id) {
    const next = state.files[index] ?? state.files[index - 1] ?? null;
    state.activeId = null;
    if (next) selectFile(next.id);
    else {
      cropper.clearImage();
      ui.stage.classList.remove('has-image');
      renderFileList();
      syncFromCropper(cropper.getState());
    }
  } else {
    renderFileList();
  }
}

async function selectFile(id) {
  const file = state.files.find((item) => item.id === id);
  if (!file) return;

  const current = activeFile();
  if (current && current.id !== id) current.snapshot = cropper.getSnapshot();

  state.activeId = id;
  renderFileList();

  try {
    await cropper.setImage(file.file);
    if (file.snapshot) cropper.applySnapshot(file.snapshot);
    else if (state.activePreset === 'original') applyPreset('original');
    ui.stage.classList.add('has-image');
    ui.outputName.value = fileStem(file.name);
    if (!file.snapshot) resetOutputWidthToNative();
    syncFromCropper(cropper.getState());
    announce(`נטענה התמונה ${file.name}`);
  } catch (error) {
    toast(error.message || 'טעינת התמונה נכשלה', 'error');
  }
}

function renderFileList() {
  ui.fileList.innerHTML = '';
  ui.fileListEmpty.hidden = state.files.length > 0;

  for (const file of state.files) {
    const item = document.createElement('li');
    item.className = 'file-item';
    item.classList.toggle('is-active', file.id === state.activeId);

    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'file-item__button';
    button.setAttribute('aria-current', file.id === state.activeId ? 'true' : 'false');
    button.innerHTML = `
      <img class="file-item__thumb" src="${file.previewUrl}" alt="" />
      <span class="file-item__meta">
        <span class="file-item__name">${escapeHtml(file.name)}</span>
        <span class="file-item__sub" ${file.savedName ? 'data-state="saved"' : ''}>
          ${file.savedName ? 'נשמר לאתר' : escapeHtml(isolate(formatBytes(file.size)))}
        </span>
      </span>
    `;
    button.addEventListener('click', () => selectFile(file.id));

    const remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'file-item__remove';
    remove.title = `הסרת ${file.name} מהרשימה`;
    remove.setAttribute('aria-label', `הסרת ${file.name} מהרשימה`);
    remove.textContent = '×';
    remove.addEventListener('click', (event) => {
      event.stopPropagation();
      removeFile(file.id);
    });

    item.append(button, remove);
    ui.fileList.append(item);
  }
}

function escapeHtml(value) {
  return String(value).replace(/[&<>"']/gu, (character) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  })[character]);
}

/* ------------------------------------------------------------------ presets */

function renderAspectPresets() {
  ui.aspectPresets.innerHTML = '';
  for (const preset of ASPECT_PRESETS) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'preset';
    button.dataset.preset = preset.id;
    button.setAttribute('aria-pressed', String(preset.id === state.activePreset));

    const ratio = preset.ratio ?? 4 / 3;
    const shape = document.createElement('span');
    shape.className = 'preset__shape';
    shape.setAttribute('aria-hidden', 'true');
    const size = 24;
    shape.style.width = `${ratio >= 1 ? size : size * ratio}px`;
    shape.style.height = `${ratio >= 1 ? size / ratio : size}px`;

    const label = document.createElement('span');
    label.className = 'preset__label';
    label.textContent = preset.label;

    button.append(shape, label);
    button.addEventListener('click', () => applyPreset(preset.id));
    ui.aspectPresets.append(button);
  }
}

function renderTargetPresets() {
  ui.targetPreset.innerHTML = '<option value="">בחירת יעד…</option>';
  for (const target of SITE_TARGETS) {
    const option = document.createElement('option');
    option.value = target.id;
    option.textContent = target.label;
    ui.targetPreset.append(option);
  }
}

function applyPreset(id) {
  const preset = ASPECT_PRESETS.find((item) => item.id === id);
  if (!preset) return;
  state.activePreset = id;

  const meta = cropper.imageMeta;
  const ratio = preset.ratio ?? (meta ? meta.width / meta.height : 1);
  cropper.setAspect(ratio);
  markActivePreset();
  syncRatioInputs(ratio);
  syncFromCropper(cropper.getState());
}

function markActivePreset() {
  for (const button of ui.aspectPresets.querySelectorAll('.preset')) {
    button.setAttribute('aria-pressed', String(button.dataset.preset === state.activePreset));
  }
}

function syncRatioInputs(ratio) {
  const [width, height] = ratioToPair(ratio);
  ui.ratioWidth.value = String(width);
  ui.ratioHeight.value = String(height);
}

/** Turns a decimal ratio into a readable pair such as 16 : 9. */
function ratioToPair(ratio, limit = 400) {
  let bestPair = [1, 1];
  let bestError = Infinity;
  for (let height = 1; height <= limit; height += 1) {
    const width = Math.round(ratio * height);
    if (width < 1) continue;
    const error = Math.abs(width / height - ratio);
    if (error < bestError - 1e-9) {
      bestError = error;
      bestPair = [width, height];
      if (error < 1e-6) break;
    }
  }
  return bestPair;
}

/* ------------------------------------------------------------ control sync  */

function syncFromCropper(snapshot) {
  state.syncing = true;

  const hasImage = Boolean(snapshot.image);
  const disabled = !hasImage || state.busy;
  for (const control of [
    ui.zoomSlider,
    ui.rotationSlider,
    ui.panXSlider,
    ui.panYSlider,
    ui.outputWidth,
    ui.outputWidthSlider,
  ]) {
    control.disabled = disabled;
  }
  ui.downloadButton.disabled = disabled;
  ui.saveButton.disabled = disabled || !state.serverAvailable;
  ui.applyAllButton.disabled = disabled || state.files.length < 2;
  ui.saveAllButton.disabled = disabled || !state.serverAvailable || state.files.length < 1;

  ui.zoomSlider.min = String(snapshot.minZoom ?? 1);
  setSliderValue(ui.zoomSlider, snapshot.zoom);
  ui.zoomOutput.textContent = isolate(`${Math.round(snapshot.zoom * 100)}%`);

  setSliderValue(ui.rotationSlider, snapshot.rotation);
  ui.rotationOutput.textContent = isolate(`${snapshot.rotation.toFixed(1).replace(/\.0$/u, '')}°`);

  const available = snapshot.panAvailable ?? { x: 0, y: 0 };
  const ratio = snapshot.panRatio ?? { x: 0, y: 0 };
  ui.panXSlider.disabled = disabled || available.x <= 0.5;
  ui.panYSlider.disabled = disabled || available.y <= 0.5;
  setSliderValue(ui.panXSlider, Math.round(ratio.x * 100));
  setSliderValue(ui.panYSlider, Math.round(ratio.y * 100));
  ui.panXOutput.textContent = describePan(ratio.x, available.x, ['שמאלה', 'ימינה']);
  ui.panYOutput.textContent = describePan(ratio.y, available.y, ['למעלה', 'למטה']);

  for (const { key } of ADJUSTMENTS) {
    const input = element(`adjust-${key}`);
    const output = element(`adjust-${key}-output`);
    if (!input) continue;
    input.disabled = disabled;
    setSliderValue(input, snapshot.adjustments[key]);
    if (output) output.textContent = formatAdjustment(key, snapshot.adjustments[key]);
  }

  ui.backgroundTransparent.checked = snapshot.background === TRANSPARENT;
  ui.backgroundColor.disabled = snapshot.background === TRANSPARENT;
  if (snapshot.background !== TRANSPARENT) ui.backgroundColor.value = snapshot.background;

  updateStageMeta(snapshot);
  updateOutputSizeLabel();
  scheduleEstimate();

  const file = activeFile();
  if (file) file.snapshot = cropper.getSnapshot();

  for (const [action, pressed] of [
    ['flip-h', snapshot.flipH],
    ['flip-v', snapshot.flipV],
    ['grid', cropper.gridVisible],
  ]) {
    const button = document.querySelector(`[data-action="${action}"]`);
    button?.setAttribute('aria-pressed', String(Boolean(pressed)));
  }

  state.syncing = false;
}

function describePan(ratio, available, [negative, positive]) {
  if (available <= 0.5) return 'אין מרווח';
  const percent = Math.round(Math.abs(ratio) * 100);
  if (percent === 0) return 'מרכז';
  return `${isolate(`${percent}%`)} ${ratio > 0 ? positive : negative}`;
}

function formatAdjustment(key, value) {
  const definition = ADJUSTMENTS.find((item) => item.key === key);
  const rounded = definition.step < 1 ? Number(value).toFixed(1) : Math.round(value);
  return isolate(`${rounded}${definition.unit}`);
}

function updateStageMeta(snapshot) {
  if (!snapshot.image) {
    ui.stageMeta.textContent = 'לא נבחרה תמונה';
    return;
  }
  const nativeWidth = cropper.getNativeCropWidth();
  const nativeHeight = Math.round(nativeWidth / snapshot.aspect);
  ui.stageMeta.textContent = [
    `מקור ${dimensions(snapshot.image.width, snapshot.image.height)}`,
    `חיתוך ${dimensions(nativeWidth, nativeHeight)}`,
    `זום ${isolate(`${Math.round(snapshot.zoom * 100)}%`)}`,
  ].join(' · ');
}

/* -------------------------------------------------------------- adjustments */

function renderAdjustments() {
  ui.adjustmentControls.innerHTML = '';
  for (const definition of ADJUSTMENTS) {
    const wrapper = document.createElement('div');
    wrapper.className = 'control';
    wrapper.innerHTML = `
      <div class="control__head">
        <label for="adjust-${definition.key}">${definition.label}</label>
        <output for="adjust-${definition.key}" id="adjust-${definition.key}-output">
          ${definition.neutral}${definition.unit}
        </output>
      </div>
      <input
        type="range"
        id="adjust-${definition.key}"
        min="${definition.min}"
        max="${definition.max}"
        step="${definition.step}"
        value="${definition.neutral}"
      />
    `;
    const input = wrapper.querySelector('input');
    input.addEventListener('input', () => {
      if (state.syncing) return;
      cropper.setAdjustment(definition.key, input.value);
    });
    input.addEventListener('dblclick', () => {
      cropper.setAdjustment(definition.key, definition.neutral);
    });
    ui.adjustmentControls.append(wrapper);
  }
}

/* ------------------------------------------------------------------- output */

function currentOutputWidth() {
  return Math.max(16, Math.round(Number(ui.outputWidth.value) || 0));
}

function currentFormat() {
  return ui.outputFormat.value;
}

function currentQuality() {
  return Number(ui.qualitySlider.value) / 100;
}

function setOutputWidth(width) {
  const value = Math.min(12000, Math.max(16, Math.round(width)));
  ui.outputWidth.value = String(value);
  setSliderValue(ui.outputWidthSlider, Math.min(Number(ui.outputWidthSlider.max), value));
  updateOutputSizeLabel();
  scheduleEstimate();
}

function resetOutputWidthToNative() {
  const native = cropper.getNativeCropWidth();
  if (native > 0) setOutputWidth(native);
}

function updateOutputSizeLabel() {
  const meta = cropper.imageMeta;
  if (!meta) {
    ui.outputSizeOutput.textContent = '—';
    return;
  }
  const width = currentOutputWidth();
  const height = Math.max(1, Math.round(width / cropper.getState().aspect));
  ui.outputSizeOutput.textContent = dimensions(width, height);
}

let estimateTimer = 0;
let estimateToken = 0;
function scheduleEstimate() {
  clearTimeout(estimateTimer);
  if (!cropper.hasImage) {
    ui.outputEstimate.textContent = 'גודל משוער: —';
    return;
  }
  estimateTimer = setTimeout(runEstimate, 450);
}

async function runEstimate() {
  if (!cropper.hasImage) return;
  const token = ++estimateToken;
  try {
    const result = await cropper.toBlob({
      width: currentOutputWidth(),
      format: currentFormat(),
      quality: currentQuality(),
    });
    if (token !== estimateToken) return;
    const parts = [
      `גודל משוער: ${isolate(formatBytes(result.blob.size))}`,
      dimensions(result.width, result.height),
      isolate(describeFormat(result.format)),
    ];

    // Exporting wider than the crop actually contains only interpolates pixels.
    const native = cropper.getNativeCropWidth();
    const upscaled = native > 0 && result.width > native * 1.02;
    if (upscaled) parts.push(`מעל הגודל המקורי (${dimensions(native, native / cropper.getState().aspect)})`);
    ui.outputEstimate.dataset.state = upscaled ? 'warn' : '';
    ui.outputEstimate.textContent = parts.join(' · ');
  } catch {
    if (token === estimateToken) ui.outputEstimate.textContent = 'גודל משוער: לא ניתן לחשב';
  }
}

function describeFormat(mime) {
  return (FORMAT_EXTENSIONS[mime] ?? mime.replace('image/', '')).toUpperCase();
}

function outputFileName() {
  const base = ui.outputName.value.trim() || fileStem(activeFile()?.name) || 'crop';
  const extension = FORMAT_EXTENSIONS[currentFormat()] ?? 'png';
  return `${base}.${extension}`;
}

async function exportCurrent() {
  return cropper.toBlob({
    width: currentOutputWidth(),
    format: currentFormat(),
    quality: currentQuality(),
  });
}

async function downloadCurrent() {
  if (!cropper.hasImage) return;
  try {
    const { blob } = await exportCurrent();
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = outputFileName();
    link.click();
    setTimeout(() => URL.revokeObjectURL(url), 1000);
    toast(`הורדה: ${isolate(link.download)}`, 'success');
  } catch (error) {
    toast(error.message || 'ההורדה נכשלה', 'error');
  }
}

function exportMetadata(result) {
  const snapshot = cropper.getState();
  return {
    sourceName: activeFile()?.name ?? '',
    width: result.width,
    height: result.height,
    aspect: Number(snapshot.aspect.toFixed(6)),
    preset: state.activePreset,
    format: result.format,
    quality: Math.round(currentQuality() * 100),
    transform: {
      zoom: Number(snapshot.zoom.toFixed(4)),
      rotation: snapshot.rotation,
      panX: Number(snapshot.panX.toFixed(5)),
      panY: Number(snapshot.panY.toFixed(5)),
      flipH: snapshot.flipH,
      flipV: snapshot.flipV,
      fit: snapshot.fit,
    },
  };
}

async function saveCurrent({ silent = false } = {}) {
  if (!cropper.hasImage) return null;
  const result = await exportCurrent();
  const dataUrl = await api.blobToDataUrl(result.blob);
  const saved = await api.uploadImage({
    fileName: outputFileName(),
    dataUrl,
    metadata: exportMetadata(result),
  });

  const file = activeFile();
  if (file) file.savedName = saved.name;
  renderFileList();
  if (!silent) {
    toast(`נשמר לאתר: ${isolate(saved.name)}`, 'success');
    announce(`התמונה נשמרה בשם ${saved.name}`);
  }
  return saved;
}

async function handleSaveClick() {
  if (state.busy) return;
  setBusy(true);
  try {
    await saveCurrent();
    await refreshLibrary();
  } catch (error) {
    handleApiError(error, 'השמירה נכשלה');
  } finally {
    setBusy(false);
  }
}

async function handleSaveAll() {
  if (state.busy || !state.files.length) return;
  setBusy(true);
  const originalId = state.activeId;
  let saved = 0;

  try {
    for (const file of [...state.files]) {
      if (file.id !== state.activeId) await selectFile(file.id);
      toast(`שומר ${saved + 1} מתוך ${state.files.length}…`);
      await saveCurrent({ silent: true });
      saved += 1;
    }
    toast(`נשמרו ${saved} תמונות לאתר`, 'success');
    await refreshLibrary();
  } catch (error) {
    handleApiError(error, `השמירה נעצרה אחרי ${saved} תמונות`);
  } finally {
    if (originalId && originalId !== state.activeId) await selectFile(originalId);
    setBusy(false);
  }
}

function applyToAllFiles() {
  const snapshot = cropper.getSnapshot();
  let count = 0;
  for (const file of state.files) {
    if (file.id === state.activeId) continue;
    file.snapshot = { ...snapshot, adjustments: { ...snapshot.adjustments } };
    count += 1;
  }
  toast(`ההגדרות הוחלו על ${count} תמונות נוספות`, 'success');
  announce(`ההגדרות הוחלו על ${count} תמונות`);
}

function setBusy(busy) {
  state.busy = busy;
  document.body.style.cursor = busy ? 'progress' : '';
  syncFromCropper(cropper.getState());
}

function handleApiError(error, fallbackMessage) {
  if (error?.status === 401) {
    ui.tokenDialog.showModal();
    toast('נדרש מפתח גישה כדי לשמור', 'error');
    return;
  }
  toast(error?.message || fallbackMessage, 'error');
}

/* ------------------------------------------------------------------ library */

async function refreshLibrary() {
  if (!state.serverAvailable) return;
  try {
    const images = await api.listImages();
    renderLibrary(images);
  } catch (error) {
    if (error.status === 401) {
      ui.libraryEmpty.textContent = 'נדרש מפתח גישה כדי להציג את הספרייה.';
      ui.libraryEmpty.hidden = false;
      ui.library.innerHTML = '';
      return;
    }
    toast(error.message || 'טעינת הספרייה נכשלה', 'error');
  }
}

function renderLibrary(images) {
  ui.library.innerHTML = '';
  ui.libraryEmpty.hidden = images.length > 0;
  ui.libraryEmpty.textContent = 'עוד לא נשמרו תמונות.';

  for (const image of images) {
    const item = document.createElement('li');
    item.className = 'library-item';

    const size = isolate(formatBytes(image.size));
    const shape = image.metadata?.width
      ? ` · ${dimensions(image.metadata.width, image.metadata.height)}`
      : '';

    item.innerHTML = `
      <img class="library-item__preview" src="${image.url}" alt="${escapeHtml(image.name)}" loading="lazy" />
      <div class="library-item__body">
        <span class="library-item__name" title="${escapeHtml(image.name)}">${escapeHtml(image.name)}</span>
        <span class="library-item__meta">${escapeHtml(size + shape)}</span>
        <div class="library-item__actions">
          <button type="button" class="button button--small" data-copy>העתקת קישור</button>
          <button type="button" class="button button--small button--danger-ghost" data-delete>מחיקה</button>
        </div>
      </div>
    `;

    item.querySelector('[data-copy]').addEventListener('click', async () => {
      const absolute = new URL(image.url, location.origin).href;
      try {
        await navigator.clipboard.writeText(absolute);
        toast('הקישור הועתק', 'success');
      } catch {
        toast(absolute);
      }
    });

    item.querySelector('[data-delete]').addEventListener('click', async () => {
      if (!confirm(`למחוק את "${image.name}" מהאתר?`)) return;
      try {
        await api.deleteImage(image.name);
        toast('התמונה נמחקה', 'success');
        await refreshLibrary();
      } catch (error) {
        handleApiError(error, 'המחיקה נכשלה');
      }
    });

    ui.library.append(item);
  }
}

/* ------------------------------------------------------------------- wiring */

function wireDropzone() {
  ui.dropzone.addEventListener('click', () => ui.fileInput.click());
  ui.dropzone.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      ui.fileInput.click();
    }
  });
  ui.fileInput.addEventListener('change', () => {
    addFiles(ui.fileInput.files);
    ui.fileInput.value = '';
  });

  for (const type of ['dragenter', 'dragover']) {
    ui.dropzone.addEventListener(type, (event) => {
      event.preventDefault();
      ui.dropzone.classList.add('is-dragover');
    });
  }
  for (const type of ['dragleave', 'dragend', 'drop']) {
    ui.dropzone.addEventListener(type, () => ui.dropzone.classList.remove('is-dragover'));
  }
  ui.dropzone.addEventListener('drop', (event) => {
    event.preventDefault();
    if (event.dataTransfer?.files?.length) addFiles(event.dataTransfer.files);
  });

  // Dropping anywhere on the page is friendlier than aiming for the box.
  window.addEventListener('dragover', (event) => event.preventDefault());
  window.addEventListener('drop', (event) => {
    if (ui.dropzone.contains(event.target)) return;
    event.preventDefault();
    if (event.dataTransfer?.files?.length) addFiles(event.dataTransfer.files);
  });

  document.addEventListener('paste', (event) => {
    const files = [...(event.clipboardData?.files ?? [])];
    if (files.length) addFiles(files);
  });
}

function wireControls() {
  ui.zoomSlider.addEventListener('input', () => {
    if (state.syncing) return;
    cropper.setZoom(Number(ui.zoomSlider.value));
  });

  ui.rotationSlider.addEventListener('input', () => {
    if (state.syncing) return;
    cropper.setRotation(Number(ui.rotationSlider.value));
  });

  ui.panXSlider.addEventListener('input', () => {
    if (state.syncing) return;
    cropper.setPanRatio({ x: Number(ui.panXSlider.value) / 100 });
  });

  ui.panYSlider.addEventListener('input', () => {
    if (state.syncing) return;
    cropper.setPanRatio({ y: Number(ui.panYSlider.value) / 100 });
  });

  for (const button of document.querySelectorAll('[data-rotate]')) {
    button.addEventListener('click', () => {
      const value = button.dataset.rotate;
      if (value === 'reset') cropper.setRotation(0);
      else cropper.rotateBy(Number(value));
    });
  }

  ui.ratioApply.addEventListener('click', () => {
    const ratio = parseAspect(`${ui.ratioWidth.value}:${ui.ratioHeight.value}`, 0);
    if (!ratio) {
      toast('יחס לא תקין', 'error');
      return;
    }
    state.activePreset = 'custom';
    markActivePreset();
    cropper.setAspect(ratio);
    syncFromCropper(cropper.getState());
    announce(`יחס החיתוך עודכן ל-${ui.ratioWidth.value} על ${ui.ratioHeight.value}`);
  });

  ui.targetPreset.addEventListener('change', () => {
    const target = SITE_TARGETS.find((item) => item.id === ui.targetPreset.value);
    if (!target) return;
    state.activePreset = 'custom';
    markActivePreset();
    cropper.setAspect(target.ratio);
    syncRatioInputs(target.ratio);
    setOutputWidth(target.width);
    syncFromCropper(cropper.getState());
    announce(`נבחר יעד ${target.label}`);
  });

  ui.resetAdjustments.addEventListener('click', () => {
    cropper.resetAdjustments();
    toast('עיצוב הצבע אופס');
  });

  ui.backgroundTransparent.addEventListener('change', () => {
    cropper.setBackground(
      ui.backgroundTransparent.checked ? TRANSPARENT : ui.backgroundColor.value,
    );
  });
  ui.backgroundColor.addEventListener('input', () => {
    ui.backgroundTransparent.checked = false;
    cropper.setBackground(ui.backgroundColor.value);
  });

  ui.outputWidthSlider.addEventListener('input', () => {
    if (state.syncing) return;
    setOutputWidth(Number(ui.outputWidthSlider.value));
  });
  ui.outputWidth.addEventListener('change', () => setOutputWidth(currentOutputWidth()));
  ui.outputNative.addEventListener('click', () => {
    resetOutputWidthToNative();
    toast(`רוחב הפלט עודכן לגודל המקורי (${formatNumber(currentOutputWidth())} פיקסלים)`);
  });

  ui.outputFormat.addEventListener('change', () => {
    ui.qualityControl.hidden = currentFormat() === 'image/png';
    scheduleEstimate();
  });
  ui.qualitySlider.addEventListener('input', () => {
    ui.qualityOutput.textContent = `${ui.qualitySlider.value}%`;
    paintRange(ui.qualitySlider);
    scheduleEstimate();
  });

  ui.downloadButton.addEventListener('click', downloadCurrent);
  ui.saveButton.addEventListener('click', handleSaveClick);
  ui.saveAllButton.addEventListener('click', handleSaveAll);
  ui.applyAllButton.addEventListener('click', applyToAllFiles);
  ui.refreshLibrary.addEventListener('click', refreshLibrary);

  ui.helpButton.addEventListener('click', () => ui.helpDialog.showModal());
  ui.tokenCancel.addEventListener('click', () => ui.tokenDialog.close());
  ui.tokenForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    api.setToken(ui.tokenInput.value.trim());
    ui.tokenInput.value = '';
    ui.tokenDialog.close();
    await checkConnection();
    await refreshLibrary();
  });
}

function wireToolbar() {
  const actions = {
    'rotate-left': () => cropper.rotateBy(-90),
    'rotate-right': () => cropper.rotateBy(90),
    'flip-h': () => cropper.toggleFlip('horizontal'),
    'flip-v': () => cropper.toggleFlip('vertical'),
    fill: () => cropper.fillFrame(),
    fit: () => cropper.fitWholeImage(),
    grid: () => {
      cropper.setGridVisible(!cropper.gridVisible);
      syncFromCropper(cropper.getState());
    },
    reset: () => {
      cropper.reset();
      announce('התמונה אופסה');
    },
  };

  for (const button of document.querySelectorAll('.toolbar [data-action]')) {
    button.addEventListener('click', () => actions[button.dataset.action]?.());
  }
}

async function checkConnection() {
  try {
    const config = await api.fetchConfig();
    state.requiresToken = Boolean(config?.requiresToken);
    state.maxUploadBytes = Number(config?.maxUploadBytes) || MAX_UPLOAD_BYTES_FALLBACK;
    ui.sizeLimitHint.textContent = `· עד ${isolate(formatBytes(state.maxUploadBytes))} לתמונה`;

    if (state.requiresToken && !api.getToken()) {
      state.serverAvailable = false;
      setConnection('locked', 'נדרש מפתח גישה');
    } else {
      state.serverAvailable = true;
      setConnection('ok', state.requiresToken ? 'מחובר · מוגן במפתח' : 'מחובר לשרת');
    }
  } catch {
    state.serverAvailable = false;
    setConnection('error', 'מצב מקומי — הורדה בלבד');
  }
  syncFromCropper(cropper.getState());
}

function setConnection(status, text) {
  ui.connectionStatus.dataset.state = status;
  ui.connectionStatus.textContent = text;
}

/* --------------------------------------------------------------------- init */

function init() {
  initTheme();
  renderAspectPresets();
  renderTargetPresets();
  renderAdjustments();
  wireDropzone();
  wireControls();
  wireToolbar();

  cropper.on('change', (snapshot) => {
    if (state.syncing) return;
    syncFromCropper(snapshot);
  });

  for (const input of document.querySelectorAll('input[type="range"]')) paintRange(input);
  ui.qualityControl.hidden = currentFormat() === 'image/png';
  syncFromCropper(cropper.getState());

  checkConnection().then(refreshLibrary);
}

init();
