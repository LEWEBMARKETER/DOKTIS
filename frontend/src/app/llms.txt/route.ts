import { lirePlanDuSite, listerLocalites, listerSpecialites } from '@/lib/directory';
import { SITE_NAME, SITE_URL } from '@/lib/site';

export const revalidate = 3600;

/**
 * Présentation de l'annuaire destinée aux assistants IA (convention llms.txt) :
 * ce que fait le site, comment on prend rendez-vous, et les pages utiles.
 */
export async function GET() {
  const [specialites, localites, plan] = await Promise.all([listerSpecialites(), listerLocalites(), lirePlanDuSite()]);
  const nomSpecialite = new Map(specialites.map((s) => [s.slug, s.nom]));
  const nomVille = new Map(localites.map((l) => [l.slug, l.ville]));

  const pages = plan.pages
    .filter((p) => p.quartier === null)
    .map((p) => `- [${nomSpecialite.get(p.specialite) ?? p.specialite} à ${nomVille.get(p.ville) ?? p.ville}](${SITE_URL}/${p.specialite}/${p.ville}) : ${p.total} praticien(s)`);

  const texte = [
    `# ${SITE_NAME}`,
    '',
    '> Annuaire de médecins, dentistes et cliniques. Chaque fiche donne les horaires, les tarifs indicatifs, les langues parlées et la prise en charge des urgences. Le patient demande son rendez-vous directement au cabinet sur WhatsApp, avec un message pré-rempli ; il n’a pas de compte à créer.',
    '',
    '## Comment prendre rendez-vous',
    '',
    '1. Ouvrir la fiche du cabinet (adresse de la forme ' + `${SITE_URL}/cabinet/<identifiant>` + ').',
    '2. Choisir le motif et le moment souhaité, puis « Demander un rendez-vous sur WhatsApp ».',
    '3. Le cabinet répond sur WhatsApp pour confirmer le créneau.',
    '',
    'Les fiches marquées « Informations non vérifiées » ont été créées par l’équipe DOKTIS et n’ont pas encore été confirmées par le cabinet.',
    '',
    '## Recherche',
    '',
    `- Recherche libre : ${SITE_URL}/recherche?q=<texte>&specialite=<slug>&ville=<slug>&ouvert=1`,
    `- Spécialités : ${specialites.map((s) => `${s.nom} (${s.slug})`).join(', ') || 'aucune pour l’instant'}`,
    `- Villes : ${localites.map((l) => `${l.ville} (${l.slug})`).join(', ') || 'aucune pour l’instant'}`,
    '',
    '## Pages par spécialité et ville',
    '',
    ...(pages.length ? pages : ['- Aucune page pour l’instant.']),
    '',
  ].join('\n');

  return new Response(texte, { headers: { 'Content-Type': 'text/plain; charset=utf-8' } });
}
