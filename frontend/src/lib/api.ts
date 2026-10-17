import { csrfHeader, ensureCsrfCookie } from './csrf';

// Toujours une URL relative : les requêtes passent par le proxy same-origin
// de Next.js (next.config.ts) pour permettre l'authentification par cookie
// de session HttpOnly. Ne jamais pointer vers une URL absolue cross-origin.
const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '/api';

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

export class ApiError extends Error {
  status: number;
  errors?: Record<string, string[]>;

  constructor(message: string, status: number, errors?: Record<string, string[]>) {
    super(message);
    this.status = status;
    this.errors = errors;
  }
}

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const { headers, ...rest } = options;
  const method = (rest.method ?? 'GET').toUpperCase();
  const isMutating = MUTATING_METHODS.has(method);

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const finalHeaders: Record<string, string> = {
    Accept: 'application/json',
    ...(isMutating ? csrfHeader() : {}),
    ...(headers as Record<string, string>),
  };

  const isFormData = rest.body instanceof FormData;
  if (!isFormData) {
    finalHeaders['Content-Type'] = 'application/json';
  }

  // "include" : le cookie de session HttpOnly Sanctum doit partir avec
  // chaque requête authentifiée (porte l'identité, plus de jeton en JS).
  const response = await fetch(`${API_URL}${path}`, { ...rest, headers: finalHeaders, credentials: 'include' });

  if (response.status === 204) {
    return undefined as T;
  }

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    throw new ApiError(data?.message ?? 'Une erreur est survenue.', response.status, data?.errors);
  }

  return data as T;
}

export const api = {
  get: <T>(path: string) => request<T>(path, { method: 'GET' }),
  post: <T>(path: string, body?: unknown) =>
    request<T>(path, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body ?? {}),
    }),
  patch: <T>(path: string, body?: unknown) =>
    request<T>(path, { method: 'PATCH', body: JSON.stringify(body ?? {}) }),
  put: <T>(path: string, body?: unknown) =>
    request<T>(path, { method: 'PUT', body: JSON.stringify(body ?? {}) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
};
