# DOKTIS

Plateforme santé en deux temps :

1. **V1 : annuaire et rendez-vous WhatsApp (en cours).** Un annuaire public de médecins, dentistes et cliniques. Le patient trouve un praticien, puis lui envoie sa demande de rendez-vous directement sur WhatsApp, avec un message pré-rempli. Aucun compte patient, aucune donnée patient stockée.
2. **V2 : SaaS de gestion de cabinet.** DOKTA Office (dossiers, agenda, facturation…) et DOKTA Patient (comptes, dossier médical) sont déjà codés dans ce dépôt, mais désactivés en V1 par des drapeaux de modules.

## Ce qui est livré en V1

**Site public** (`frontend/src/app/(public)`)
- Accueil avec recherche (spécialité, ville, texte libre, « ouvert maintenant », urgences).
- Pages indexables `/{spécialité}/{ville}` et `/{spécialité}/{ville}/{quartier}`, générées uniquement pour les combinaisons non vides.
- Fiche cabinet `/cabinet/{slug}` : horaires, soins et tarifs indicatifs, praticiens, itinéraire, et formulaire « Demander un rendez-vous sur WhatsApp » (lien `wa.me` pré-rempli, sans API WhatsApp).
- Mention « Informations non vérifiées » et revendication de fiche par le praticien.
- `sitemap.xml`, `robots.txt`, `llms.txt` (présentation pour les assistants IA) et données structurées schema.org sur chaque page.

**Espace praticien** (`/pro`) : page « Ma fiche annuaire » (informations, WhatsApp, spécialités, horaires, tarifs) et statistiques anonymes (vues, demandes WhatsApp, appels).

**Super Admin** (`/pro/cabinets`, `/pro/revendications`) : création de fiches pré-remplies, validation des inscriptions, traitement des revendications (le compte du praticien est créé à l'approbation).

**Données minimales** : l'annuaire ne stocke que des informations professionnelles et des compteurs journaliers agrégés (`cabinet_statistiques_jour`), sans aucune donnée sur le visiteur.

## Modules activables

| Module | Variable API (`backend/.env`) | Variable interface (`frontend/.env.local`) | V1 |
|---|---|---|---|
| Annuaire | toujours actif | toujours actif | ✅ |
| SaaS (DOKTA Office) | `DOKTA_MODULE_SAAS` | `NEXT_PUBLIC_DOKTA_MODULE_SAAS` | désactivé |
| Patient (comptes, dossier, messagerie, avis) | `DOKTA_MODULE_PATIENT` | `NEXT_PUBLIC_DOKTA_MODULE_PATIENT` | désactivé |

Un module désactivé répond 404 côté API et disparaît du menu de l'espace praticien. Le fuseau utilisé pour « ouvert maintenant » est réglé par `DOKTA_FUSEAU_LOCAL` (par défaut `Africa/Douala`).

## Architecture

- **Frontend** (`frontend/`) : Next.js (App Router) + TypeScript + Tailwind CSS. Site public rendu côté serveur (régénéré toutes les 5 minutes) et espace praticien sous `/pro`.
- **Backend** (`backend/`) : Laravel + PostgreSQL, API REST multi-tenant (isolation par `cabinet_id` via un scope global Eloquent).
- **Authentification** : jetons Sanctum, deux types de comptes indépendants partageant le même mécanisme — `User` (personnel de cabinet) et `PatientAccount` (compte DOKTA Patient, rattachable à un dossier médical dans plusieurs cabinets).
- **Temps réel** : Laravel Reverb (WebSockets), canaux privés par cabinet, par patient et par conversation (voir `routes/channels.php`).
- **Stockage documents** : disque local en développement, [Supabase Storage](https://supabase.com/storage) (compatible S3) en production.
- **Paiements prévus** : Mobile Money (Airtel Money, Moov Money) côté DOKTA Patient — intégration à finaliser.

## Modules du code (V2 incluse)

### DOKTA Office (désactivé en V1)
Authentification et rôles (Administrateur, Médecin/Dentiste, Secrétaire, Assistant médical) · tableau de bord · dossiers patients · agenda avec détection de conflit et reprogrammation · consultations · schéma dentaire interactif (notation FDI) · documents (photos, radios, PDF) · plans de traitement · ordonnances et modèles · devis convertibles en factures · facturation avec paiement échelonné · comptabilité (dépenses, journal de caisse, export CSV) · paramètres (horaires, services/tarifs, mutuelles partenaires) · messagerie avec les patients · avis clients · tickets de support.

### DOKTA Patient (API prête, désactivée en V1)
Création de compte et connexion (email ou téléphone) · recherche et prise de rendez-vous auprès d'un cabinet (le dossier médical est créé/lié automatiquement) · suivi et annulation de rendez-vous · consultation du dossier médical (ordonnances, documents, factures, plans de traitement) à travers tous les cabinets visités · messagerie sécurisée avec le cabinet · dépôt d'avis sur un cabinet.

### DOKTA Directory (V1, en ligne)
Recherche publique par ville, quartier, spécialité, langue parlée, urgences, PMR, assurance acceptée et « ouvert maintenant » · fiche par slug · localités et plan du site · compteurs anonymes · revendication de fiche.

### Super Admin DOKTA
Fiches de l'annuaire et revendications · statistiques nationales · gestion des cabinets et de leurs abonnements · tickets de support · incidents · journal d'audit (`journal_actions`).

## Démarrage rapide

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan reverb:install   # si besoin de régénérer les identifiants Reverb
# Configurer une base PostgreSQL puis :
php artisan migrate --seed
php artisan serve
```

Pour le temps réel, lancer en parallèle : `php artisan reverb:start`.

Le seeder crée un cabinet de démonstration vérifié, quatre fiches non revendiquées (Douala et Yaoundé, noms fictifs) et les comptes suivants :

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@cabinet-etoile.dokta | password |
| Médecin | medecin@cabinet-etoile.dokta | password |
| Secrétaire | secretaire@cabinet-etoile.dokta | password |
| Assistant | assistant@cabinet-etoile.dokta | password |
| Super administrateur DOKTA | superadmin@dokta.app | password |

Ainsi qu'un compte **DOKTA Patient** de démonstration (utilisable seulement avec `DOKTA_MODULE_PATIENT=true`) :

| Email ou téléphone | Mot de passe |
|---|---|
| sonia@patient.dokta / 690112233 | password |

### Frontend (Next.js)

```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

Le site public est sur `http://localhost:3000`, l'espace praticien sur `http://localhost:3000/pro` (API attendue sur `http://localhost:8000/api`, configurable via `NEXT_PUBLIC_API_URL` ; adresse publique du site via `NEXT_PUBLIC_SITE_URL`).

### Tests backend

```bash
cd backend
php artisan test
```

## Aperçu de l'API

- `POST /api/auth/register-cabinet`, `/api/auth/login` — DOKTA Office.
- `POST /api/patient/auth/register`, `/api/patient/auth/login` — DOKTA Patient.
- `GET /api/directory/cabinets` (filtres `q`, `specialite`, `ville`, `quartier`, `ouvert`, `urgence`, `whatsapp`…), `/api/directory/cabinets/{id|slug}`, `/api/directory/localites`, `/api/directory/plan-du-site` — annuaire public.
- `POST /api/directory/cabinets/{slug}/evenements` (`type` = `vue`, `clic_whatsapp`, `clic_telephone`) et `/api/directory/cabinets/{slug}/revendications`.
- `GET /api/fiche/statistiques` — statistiques de la fiche du cabinet connecté.
- `POST /api/cabinets`, `GET /api/super-admin/revendications`, `POST /api/super-admin/revendications/{id}/approuver|rejeter` — Super Admin.
- `GET /api/super-admin/statistiques` — Super Admin DOKTA (rôle `super_admin`).

Le fichier `routes/api.php` regroupe l'ensemble des routes par module.

## Vision long terme

Page de réservation gratuite pour les praticiens (créneaux libres ou pris), pharmacies de garde, activation du SaaS en V2, application praticien mobile, téléconsultation, IA d'aide au diagnostic, gestion de stock (voir le cahier des charges).
