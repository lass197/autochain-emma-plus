const TOKEN_KEY = 'autochain_token';

export function getToken() {
    return localStorage.getItem(TOKEN_KEY);
}

export function setToken(token) {
    localStorage.setItem(TOKEN_KEY, token);
}

export function clearToken() {
    localStorage.removeItem(TOKEN_KEY);
}

export async function api(path, options = {}) {
    const headers = {
        Accept: 'application/json',
        ...(options.body instanceof FormData ? {} : { 'Content-Type': 'application/json' }),
        ...options.headers,
    };

    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`/api/v1${path}`, {
        ...options,
        headers,
        body:
            options.body && !(options.body instanceof FormData)
                ? JSON.stringify(options.body)
                : options.body,
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
        ? await response.json()
        : await response.text();

    if (!response.ok) {
        const message =
            typeof payload === 'object'
                ? payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Erreur API'
                : 'Erreur API';
        const error = new Error(message);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

export async function downloadFile(path, filename = 'document') {
    const headers = { Accept: '*/*' };
    const token = getToken();
    if (token) {
        headers.Authorization = `Bearer ${token}`;
    }

    const response = await fetch(`/api/v1${path}`, { headers });
    if (!response.ok) {
        throw new Error('Téléchargement impossible.');
    }

    const blob = await response.blob();
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.click();
    URL.revokeObjectURL(url);
}
