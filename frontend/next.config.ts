import type { NextConfig } from "next";

// URL interne (serveur à serveur) de l'API Laravel. Volontairement sans le
// préfixe NEXT_PUBLIC_ : jamais exposée au navigateur, seul ce serveur Next.js
// s'en sert pour relayer les requêtes /api et /sanctum.
const BACKEND_URL = process.env.BACKEND_INTERNAL_URL ?? "http://localhost:8000";

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
};

export default nextConfig;
