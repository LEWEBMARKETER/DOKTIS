// Jeton anti-CSRF Sanctum (double-submit cookie) : commun à DOKTIS Office et
// DOKTIS Patient, puisque les deux partagent la même session de navigateur
// (deux gardes distincts, un seul cookie de session côté serveur). Ce jeton
// n'identifie personne — il protège uniquement contre les requêtes forgées
// par un site tiers — donc le partager entre les deux apps ne compromet pas
// leur cloisonnement applicatif (gardes "web" / "patient" séparés côté API).

function readCookie(name: string): string | null {
  if (typeof document === 'undefined') return null;
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

let pending: Promise<void> | null = null;

export async function ensureCsrfCookie(): Promise<void> {
  if (readCookie('XSRF-TOKEN')) return;

  pending ??= fetch('/sanctum/csrf-cookie', { credentials: 'include' })
    .then(() => undefined)
    .finally(() => {
      pending = null;
    });

  await pending;
}

export function csrfHeader(): Record<string, string> {
  const token = readCookie('XSRF-TOKEN');
  return token ? { 'X-XSRF-TOKEN': token } : {};
}
