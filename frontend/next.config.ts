import type { NextConfig } from "next";

// URL interne (serveur à serveur) de l'API Laravel. Volontairement sans le
// préfixe NEXT_PUBLIC_ : jamais exposée au navigateur, seul ce serveur Next.js
// s'en sert pour relayer les requêtes /api et /sanctum.
const BACKEND_URL = process.env.BACKEND_INTERNAL_URL ?? "http://localhost:8000";

// CSP volontairement stricte : tout le trafic applicatif (API, pages, assets)
// passe par cette même origine via les rewrites ci-dessous, et l'app ne
// charge aucun script/police/image tiers — donc pas besoin d'élargir
// connect-src/script-src au-delà de 'self'.
const CSP = [
  "default-src 'self'",
  "script-src 'self' 'unsafe-inline'",
  "style-src 'self' 'unsafe-inline'",
  "img-src 'self' data: blob:",
  "font-src 'self' data:",
  "connect-src 'self'",
  "frame-ancestors 'none'",
  "base-uri 'self'",
  "form-action 'self'",
].join('; ');

const nextConfig: NextConfig = {
  // Relaie /api/* et /sanctum/* vers Laravel en conservant la même origine
  // navigateur que le frontend. C'est ce qui permet une authentification par
  // cookie de session HttpOnly (SameSite=Lax) sans aucune configuration
  // cross-site : pour le navigateur, l'API n'est jamais un tiers, quel que
  // soit le domaine réel du backend en production.
  async rewrites() {
    return [
      { source: "/api/:path*", destination: `${BACKEND_URL}/api/:path*` },
      { source: "/sanctum/:path*", destination: `${BACKEND_URL}/sanctum/:path*` },
    ];
  },
  // Ancienne route de la liste publique (avant le repositionnement
  // Directory-first) : redirige proprement vers la nouvelle page de
  // recherche, en conservant les paramètres de requête existants. La
  // fiche d'un cabinet (/directory/{id}) est gérée par
  // app/directory/[id]/page.tsx (résolution id → slug, pas une simple
  // redirection statique).
  async redirects() {
    return [{ source: '/directory', destination: '/recherche', permanent: true }];
  },
  async headers() {
    return [
      {
        source: "/:path*",
        headers: [
          { key: "Content-Security-Policy", value: CSP },
          { key: "X-Content-Type-Options", value: "nosniff" },
          { key: "X-Frame-Options", value: "DENY" },
          { key: "Referrer-Policy", value: "strict-origin-when-cross-origin" },
        ],
      },
    ];
  },
};

export default nextConfig;
