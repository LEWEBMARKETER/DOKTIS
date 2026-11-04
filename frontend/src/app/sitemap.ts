import type { MetadataRoute } from 'next';
import { lirePlanDuSite } from '@/lib/directory';
import { SITE_URL } from '@/lib/site';

export const revalidate = 3600;

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const plan = await lirePlanDuSite();

  return [
    { url: SITE_URL, changeFrequency: 'daily', priority: 1 },
    ...plan.pages.map((p) => ({
      url: `${SITE_URL}/${p.specialite}/${p.ville}${p.quartier ? `/${p.quartier}` : ''}`,
      changeFrequency: 'weekly' as const,
      priority: p.quartier ? 0.6 : 0.8,
    })),
    ...plan.cabinets.map((c) => ({
      url: `${SITE_URL}/cabinet/${c.slug}`,
      lastModified: c.updated_at,
      changeFrequency: 'weekly' as const,
      priority: 0.7,
    })),
  ];
}
