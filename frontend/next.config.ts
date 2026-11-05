import type { NextConfig } from "next";

/**
 * Adresse interne de l'API Laravel, vue depuis le serveur Next.js.
 * Sur Render : API_HOSTPORT (« doktis-api:10000 ») est fourni par le
 * blueprint et passe par le réseau privé. En local : http://localhost:8000.
 */
const API_ORIGIN =
  process.env.API_ORIGIN ??
  (process.env.API_HOSTPORT ? `http://${process.env.API_HOSTPORT}` : "http://localhost:8000");

const nextConfig: NextConfig = {
  env: {
    // Adresse publique du site (sitemap, liens canoniques, QR codes).
    // Render fournit RENDER_EXTERNAL_URL tant qu'aucun domaine n'est configuré.
    DOKTIS_SITE_URL:
      process.env.NEXT_PUBLIC_SITE_URL ?? process.env.RENDER_EXTERNAL_URL ?? "http://localhost:3000",
    DOKTIS_API_ORIGIN: API_ORIGIN,
  },
  // Le navigateur appelle /api sur le même domaine que le site : Next.js relaie
  // vers l'API. Pas de CORS à configurer et l'API peut rester sur le réseau privé.
  async rewrites() {
    return [{ source: "/api/:path*", destination: `${API_ORIGIN}/api/:path*` }];
  },
};

export default nextConfig;
