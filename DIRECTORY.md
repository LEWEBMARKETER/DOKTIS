# DOKTIS Directory-first — documentation du repositionnement

Ce document décrit le repositionnement de DOKTIS autour de l'annuaire
public (« Directory-first »), réalisé sur la branche `feature/directory-first`
(voir aussi `SECURITY.md` pour les mécanismes de sécurité transverses).
Il complète le README (vision produit 3-en-1 d'origine) sans le remplacer :
DOKTIS Office et DOKTIS Patient existent toujours dans le code, simplement
désactivés par défaut en attendant le lancement commercial de l'annuaire.

## 1. Principe

Trois espaces, un seul produit :

- **Annuaire public** (`/`, `/recherche`, `/structures`, `/professionnels`,
  `/fiche/[slug]`, `/connexion`, `/contact`) — ouvert à tous, sans compte.
- **Espace partenaire** (`/partenaire/*`) — réservé aux structures et
  professionnels dont la fiche a été créée et vérifiée par DOKTIS.
- **Administration DOKTIS** (`/admin/*`) — réservée au rôle `super_admin` :
  création/vérification/publication des fiches, invitations partenaires,
  abonnements, supervision des demandes de rendez-vous.

DOKTIS Office (`/office/*`, ex-racine du site) et le dossier médical de
DOKTIS Patient restent dans le code mais sont désactivés par défaut côté
serveur (voir § 4, flags de fonctionnalité) : ni l'auto-inscription
(`/office/register`) ni les routes métier (patients, agenda, facturation…)
ne répondent tant que `DOKTIS_OFFICE_ENABLED` n'est pas à `true`.

## 2. Arborescence des routes (frontend)

| Avant | Après | Remarque |
|---|---|---|
| `/` (tableau de bord Office) | `/office` | Office déplacé, racine libérée |
| `/login`, `/register` | `/office/login`, `/office/register` | |
| `/directory`, `/directory/[id]` | `/recherche`, `/fiche/[slug]` | redirections automatiques (§ 3) |
| `/cabinets` (super_admin) | `/admin/structures` + `/admin/professionnels` + `/admin/fiches` | remplacé par un cycle de vie de fiche plus riche |
| — | `/partenaire/connexion`, `/partenaire/activer`, `/partenaire/tableau-de-bord`, `/partenaire/ma-fiche`, `/partenaire/rendez-vous`, `/partenaire/statistiques`, `/partenaire/abonnement` | nouveau |
| — | `/admin/tableau-de-bord`, `/admin/structures`, `/admin/professionnels`, `/admin/fiches`, `/admin/fiches/[id]`, `/admin/rendez-vous`, `/admin/abonnements`, `/admin/statistiques`, `/admin/utilisateurs`, `/admin/parametres` | nouveau |
| — | `/`, `/recherche`, `/structures`, `/professionnels`, `/fiche/[slug]`, `/connexion`, `/contact` | nouveau (annuaire public) |

`/patient/*` est inchangé dans sa structure ; seules les routes de dossier
médical (`/patient/rendez-vous`, `/patient/dossier`) dépendent désormais de
`DOKTIS_PATIENT_MEDICAL_ENABLED` côté API (la page reste joignable, l'appel
API renvoie 403 tant que le flag est désactivé — amélioration UX possible en
V2 : masquer ces liens de navigation quand le flag est éteint).

## 3. Redirections

- `/directory` → `/recherche` (redirection statique, `next.config.ts`,
  conserve les paramètres de requête).
- `/directory/{id}` → `/fiche/{slug}` : résolution **dynamique** (pas une
  redirection statique) — `app/directory/[id]/page.tsx` interroge l'API
  avec l'ancien identifiant numérique, récupère le `slug` correspondant,
  puis redirige. Un ancien lien externe ou indexé continue donc de
  fonctionner. `notFound()` si le cabinet n'existe plus.
- Aucune boucle possible : les deux routes de destination (`/recherche`,
  `/fiche/[slug]`) ne redirigent jamais elles-mêmes.

## 4. Flags de fonctionnalité (serveur)

Définis dans `backend/config/features.php`, vérifiés par le middleware
`App\Http\Middleware\EnsureFeatureEnabled` (alias `feature:<nom>`) sur les
groupes de routes concernés dans `routes/api.php` — jamais par un simple
masquage frontend (une route désactivée répond `403` même en tapant
directement l'URL de l'API).

| Variable | Défaut | Effet quand `false` |
|---|---|---|
| `DOKTIS_DIRECTORY_ENABLED` | `true` | N'est lu par aucune route actuellement (l'annuaire public n'est jamais gated) ; prévu pour une future maintenance planifiée. |
| `DOKTIS_OFFICE_ENABLED` | `false` | `POST /auth/register-cabinet` et tout le module métier Office (patients, agenda, consultations, documents, ordonnances, factures, devis, dépenses, comptabilité, paramètres cabinet, `GET /users`) répondent `403`. Le rôle `partenaire` en est exclu même quand le flag est à `true` (il n'a jamais accès au module Office). `/auth/logout` et `/auth/me` restent toujours disponibles (infrastructure d'authentification partagée, pas un module produit). |
| `DOKTIS_PATIENT_MEDICAL_ENABLED` | `false` | Les routes `/patient/rendez-vous`, `/patient/mes-dossiers`, `/patient/mes-ordonnances`, `/patient/mes-documents`, `/patient/mes-factures`, `/patient/mes-plans-traitement` répondent `403`. Messagerie et avis (`/patient/mes-avis`, `/patient/conversations`) restent disponibles. |

Désactiver un module ne supprime et ne modifie **aucune donnée** : il rend
seulement ses routes inaccessibles.

## 5. Modèles et migrations ajoutés

Aucune migration existante n'a été modifiée a posteriori ; tout est additif
(voir `backend/database/migrations/2026_10_23_*`) :

- `cabinets` : `categorie_fiche` (`structure` | `professionnel_independant`),
  `statut_verification` (`non_verifiee` | `en_attente_verification` |
  `verifiee`), `statut_publication` (`brouillon` | `publiee` | `suspendue` |
  `archivee`) — remplace `visible_annuaire` comme source de vérité de la
  visibilité publique (`Cabinet::scopePubliees()`/`estPubliee()`), ce champ
  étant conservé en lecture pour compatibilité mais plus modifiable par le
  cabinet lui-même. Correction au passage : `pays` par défaut passe de
  « Cameroun » à « Gabon ».
- `users` : nouveau rôle `partenaire` ajouté à la contrainte d'énumération
  existante (aucune valeur retirée).
- `abonnements` : `produit` (`office` | `directory_structure` |
  `directory_professionnel`), nouveau statut `expire`.
- **Nouvelles tables**, toutes volontairement indépendantes du module
  médical : `invitations_partenaire`, `demandes_rendez_vous` (+
  `date_proposee`/`creneau_propose`), `demandes_modification_fiche`,
  `visites_fiche` + `visites_fiche_empreintes` (dédoublonnage des
  visiteurs uniques, purgée par `php artisan visites:purger-empreintes`,
  planifiée quotidiennement), `paiements_abonnement`.

Détail du raisonnement qui a guidé chaque choix de modélisation (réutiliser
`Cabinet` plutôt que créer un modèle `Professionnel`, séparer
`DemandeRendezVous` du `RendezVous` médical, etc.) : voir l'historique de
conversation de cette branche ou les docblocks des migrations/modèles
concernés, qui reprennent chacun la justification en commentaire.

## 6. Fonctionnalités réutilisées sans modification de comportement

- Recherche/filtre de l'annuaire (`Api\Directory\CabinetController`),
  étendue avec `categorie`/`tri=recent`, jamais cassée pour les appelants
  existants (défauts inchangés).
- Isolation multi-tenant (`CabinetScope`/`BelongsToCabinet`) : les nouveaux
  modèles (`DemandeRendezVous`, `DemandeModificationFiche`,
  `InvitationPartenaire`) l'utilisent telle quelle — un partenaire ne voit
  jamais que les données de sa propre fiche, sans code supplémentaire.
- Authentification : le rôle `partenaire` est un `User` ordinaire sur le
  même garde `web` que le personnel Office et le super admin — aucune
  nouvelle garde, aucun nouvel endpoint de connexion (`POST /auth/login`
  sert les trois profils, la redirection post-connexion dépend du rôle
  renvoyé).
- Abonnements (`Abonnement`, `Api\SuperAdmin\AbonnementController`) :
  étendu avec `produit` plutôt que dupliqué, rétrocompatible avec les
  abonnements Office existants.
- Journal d'audit (`JournalAction`) : toutes les actions Directory
  significatives y sont journalisées (`fiche.creee`, `fiche.publiee`,
  `partenaire.invitation_envoyee`, `partenaire.compte_active`,
  `partenaire.demande_rdv_confirmee`, `abonnement.paiement_enregistre`, etc.).
- Composants UI (`frontend/src/components/ui.tsx`) réutilisés tels quels
  dans toutes les nouvelles pages Admin/Partenaire.

## 7. Contrôles de sécurité appliqués (spécifiques à ce lot)

Voir aussi `SECURITY.md` pour les mécanismes transverses déjà en place
(auth par cookie, CSRF, isolation multi-tenant, documents, concurrence
RDV…), tous conservés sans modification.

- **Création de fiche exclusive à l'administration** : aucune route
  partenaire ne permet de créer, publier, dépublier ou archiver une fiche
  (vérifié par test — `FicheAdministrationTest`).
- **Invitation à usage unique et durée limitée** : jeton en clair jamais
  stocké (seul son hash l'est, comme un jeton de réinitialisation de mot de
  passe), vérifié non expiré/non utilisé avant toute activation
  (`InvitationPartenaireTest`).
- **Isolation du rôle partenaire** : explicitement exclu du module Office
  même quand celui-ci est activé (liste blanche de rôles sur la route
  `feature:office`), jamais d'accès aux dossiers médicaux.
- **Demandes de modification de fiche** : liste blanche stricte de champs
  modifiables côté admin (`ModificationFicheController::champsModifiables`)
  — un partenaire ne peut jamais faire passer un changement de statut ou un
  champ hors liste via ce canal.
- **Anti-spam sur les demandes de rendez-vous publiques** : rejet d'un
  doublon (même téléphone, même cabinet, même date, encore en attente),
  endpoints publics (demande, visite, clic) limités en fréquence
  (`throttle:60,1`).
- **Aucune date passée acceptée** pour une demande de rendez-vous
  (validation serveur `after_or_equal:today`).
- **Statistiques respectueuses de la vie privée** : jamais d'adresse IP
  complète stockée (empreinte hachée, purgée périodiquement), robots connus
  exclus du comptage, dédoublonnage quotidien des visiteurs uniques — voir
  `App\Services\CollecteStatistiquesFiche`.
- **Paiements d'abonnement** : uniquement constatés manuellement par un
  administrateur (Mobile Money/virement/espèces) — aucune intégration de
  paiement en ligne, jamais de paiement simulé comme réussi.
- **Correction d'un bug latent découvert pendant ce lot** : `CabinetScope`
  comparait l'attribut `role` (casté en instance d'enum) à une chaîne via
  `===`, toujours faux — le contournement du scope pour `super_admin`
  n'était donc jamais atteint. Sans fuite de données (une comparaison à
  `cabinet_id = NULL` ne retourne jamais rien), mais rendait vides les vues
  plateforme qui en dépendaient. Corrigé dans `CabinetScope::apply()`.

## 8. Tests exécutés et résultats

### Automatisés (backend)

`php artisan test` — **96/96 tests passés**, dont 32 nouveaux spécifiques à
ce lot :

- `tests/Feature/Directory/FicheAdministrationTest.php` (6) — création
  structure/professionnel, refus administrateur/partenaire, vérification
  obligatoire avant publication, suspension/archivage sans perte de données.
- `tests/Feature/Directory/InvitationPartenaireTest.php` (4) — activation
  réussie, jeton invalide, lien déjà utilisé, aucun droit Office pour un
  partenaire actif.
- `tests/Feature/Directory/StatistiquesPartenaireTest.php` (4) — lecture de
  ses propres statistiques, isolation entre partenaires, incrémentation des
  compteurs via les routes publiques, exclusion d'un robot connu.
- `tests/Feature/Directory/DemandeRendezVousTest.php` (7) — soumission sans
  compte, fiche non publiée introuvable, date passée rejetée, doublon
  rejeté, confirmation/refus par le partenaire, isolation entre cabinets.
- `tests/Feature/Directory/AbonnementDirectoryTest.php` (5) — création
  d'un abonnement Directory, rétrocompatibilité Office, calcul de la date
  d'expiration après paiement, pas de suppression de fiche à l'expiration,
  lecture seule côté partenaire.
- `tests/Feature/Directory/FeatureFlagsTest.php` (4) — 403 sur Office/
  dossier médical patient désactivés, auto-inscription bloquée,
  administration et annuaire toujours accessibles.
- `tests/Feature/DirectoryTest.php` — 2 tests ajoutés (filtre catégorie,
  accès par slug) aux 3 existants, tous mis à jour pour les nouveaux champs
  de statut sans changer leur intention.

Les 64 tests pré-existants (sécurité, isolation multi-tenant, facturation,
rendez-vous médical, etc.) restent verts sans modification de leur logique
— seules les données de test de `DirectoryTest` ont été adaptées au nouveau
modèle de statuts.

### Vérifié manuellement (navigateur réel, hors suite automatisée)

Parcours bout en bout via Playwright contre les serveurs de développement
réels (Laravel + Next.js, base de données réelle) :
1. Accueil public → recherche → fiche → soumission d'une demande de
   rendez-vous invité (sans compte) → vérifié en base (`statut: en_attente`,
   aucune donnée médicale).
2. Connexion super admin → les 9 pages `/admin/*` + le détail d'une fiche
   se chargent sans erreur console/page.
3. Admin crée une fiche professionnel → vérifie → publie → invite un
   partenaire → le partenaire active son compte via le lien réel → atterrit
   sur son tableau de bord → consulte sa fiche et ses statistiques.

### Build et analyse statique

- `npx tsc --noEmit` : 0 erreur.
- `npx eslint .` : 0 erreur (avertissements `react-hooks/set-state-in-effect`
  préexistants ailleurs dans le code, même motif reproduit à l'identique
  dans le nouveau code pour rester cohérent avec le style établi).
- `npm run build` : succès, 44 routes générées.
- `composer audit` : aucune vulnérabilité.

### Non exécuté / sans objet

- Tests de charge sur les endpoints publics (demande de rendez-vous, suivi
  de visite) : hors périmètre outillage disponible dans cette session.
- Validation juridique de la politique de confidentialité/collecte
  statistique : n'est pas un test technique (voir `SECURITY.md` § 9).

## 9. Déploiement

1. `php artisan migrate` (inclut les migrations Directory-first listées au
   § 5 — purement additives, aucun risque sur les données existantes).
2. Définir les nouvelles variables d'environnement (voir
   `backend/.env.example`) : `DOKTIS_DIRECTORY_ENABLED=true`,
   `DOKTIS_OFFICE_ENABLED=false`, `DOKTIS_PATIENT_MEDICAL_ENABLED=false`
   pour un lancement Directory-first strict ; ajuster
   `DOKTIS_JOURS_GRACE_ABONNEMENT` et `DOKTIS_INVITATION_EXPIRE_JOURS` si
   besoin.
3. Planifier `php artisan schedule:run` (cron habituel Laravel) pour que
   `visites:purger-empreintes` s'exécute quotidiennement.
4. Déployer le frontend : aucune nouvelle variable requise au-delà de
   celles déjà documentées (`NEXT_PUBLIC_API_URL`, `BACKEND_INTERNAL_URL`).
5. Vérifier en pré-production : accueil public, recherche, fiche + demande
   de rendez-vous invité, connexion admin, connexion partenaire (après une
   invitation de test sur une fiche fictive) — avant bascule du trafic réel.

## 10. DOKTIS Office — ce qui est conservé

Aucune fonctionnalité Office n'a été supprimée. Tout le code métier
(dossiers patients, agenda avec détection de conflit, consultations, schéma
dentaire, documents, ordonnances, devis/factures/paiements échelonnés,
comptabilité, paramètres cabinet, gestion d'équipe, messagerie et avis côté
cabinet, tickets de support) reste en place sous `/office/*` et derrière
`DOKTIS_OFFICE_ENABLED`. Le réactiver (mettre le flag à `true` et rouvrir
l'auto-inscription si souhaité) ne nécessite aucune migration ni
changement de code — uniquement un changement de configuration, revu et
déployé volontairement.
