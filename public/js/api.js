/** Thin client for the studio API, including the optional access token. */

const TOKEN_STORAGE_KEY = 'image-studio-token';

export function getToken() {
  try {
    return sessionStorage.getItem(TOKEN_STORAGE_KEY) ?? '';
  } catch {
    return '';
  }
}

export function setToken(token) {
  try {
    if (token) sessionStorage.setItem(TOKEN_STORAGE_KEY, token);
    else sessionStorage.removeItem(TOKEN_STORAGE_KEY);
  } catch {
    /* private browsing: the token simply is not remembered */
  }
}

async function request(url, options = {}) {
  const headers = { ...(options.headers ?? {}) };
  const token = getToken();
  if (token) headers['x-admin-token'] = token;

  const response = await fetch(url, { ...options, headers });
  const isJson = (response.headers.get('content-type') ?? '').includes('application/json');
  const payload = isJson ? await response.json().catch(() => null) : null;

  if (!response.ok) {
    const error = new Error(payload?.error ?? `הבקשה נכשלה (${response.status})`);
    error.status = response.status;
    throw error;
  }
  return payload;
}

export function fetchConfig() {
  return request('/api/config');
}

export async function listImages() {
  const payload = await request('/api/images');
  return payload?.images ?? [];
}

export function uploadImage({ fileName, dataUrl, metadata }) {
  return request('/api/images', {
    method: 'POST',
    headers: { 'content-type': 'application/json' },
    body: JSON.stringify({ fileName, dataUrl, metadata }),
  });
}

export function deleteImage(name) {
  return request(`/api/images/${encodeURIComponent(name)}`, { method: 'DELETE' });
}

export function blobToDataUrl(blob) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result));
    reader.onerror = () => reject(new Error('קריאת הקובץ נכשלה'));
    reader.readAsDataURL(blob);
  });
}
