import { ApiError } from '@/lib/api';
import { csrfHeader, ensureCsrfCookie } from '@/lib/csrf';

export { ApiError };

// Toujours une URL relative : voir lib/api.ts pour le détail (proxy Next.js
// same-origin, requis pour le cookie de session HttpOnly Sanctum).
const API_URL = process.env.NEXT_PUBLIC_API_URL ?? '/api';

const MUTATING_METHODS = new Set(['POST', 'PUT', 'PATCH', 'DELETE']);

async function request<T>(path: string, options: RequestInit = {}): Promise<T> {
  const { headers, ...rest } = options;
  const method = (rest.method ?? 'GET').toUpperCase();
  const isMutating = MUTATING_METHODS.has(method);

  if (isMutating) {
    await ensureCsrfCookie();
  }

  const isFormData = rest.body instanceof FormData;
  const finalHeaders: Record<string, string> = {
    Accept: 'application/json',
    ...(isFormData ? {} : { 'Content-Type': 'application/json' }),
    ...(isMutating ? csrfHeader() : {}),
    ...(headers as Record<string, string>),
  };

  // "include" : le compte DOKTIS Patient s'authentifie via le même cookie
  // de session HttpOnly que DOKTIS Office (garde "patient" dédié côté API,
  // voir backend/config/auth.php) — plus de jeton stocké côté client.
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

export const patientApi = {
  get: <T>(path: string) => request<T>(path, { method: 'GET' }),
  post: <T>(path: string, body?: unknown) =>
    request<T>(path, {
      method: 'POST',
      body: body instanceof FormData ? body : JSON.stringify(body ?? {}),
    }),
  patch: <T>(path: string, body?: unknown) => request<T>(path, { method: 'PATCH', body: JSON.stringify(body ?? {}) }),
  delete: <T>(path: string) => request<T>(path, { method: 'DELETE' }),
};
