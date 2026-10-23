# DOKTIS

Plateforme numérique de gestion des cabinets médicaux et de prise de rendez-vous, composée de trois modules interconnectés partageant les mêmes données en temps réel :

- **DOKTIS Office** — le SaaS de gestion des cabinets (dossiers patients, agenda, facturation, comptabilité...).
- **DOKTIS Patient** — l'application destinée aux patients (recherche de cabinet, prise de rendez-vous, dossier médical, messagerie).
- **DOKTIS Directory** — l'annuaire public des professionnels de santé.

Un **Super Admin DOKTIS** transverse gère les cabinets, abonnements, le support et les statistiques nationales.

Ce dépôt contient les fondations backend des trois modules (API Laravel + schéma de données) et les trois applications front-end (Next.js) : annuaire public, espace partenaire, espace cabinet (DOKTIS Office) et espace patient.

> **Positionnement commercial actuel : Directory-first.** Le premier produit lancé est l'annuaire public (recherche de structures/professionnels, prise de contact, abonnement annuel) sous la marque unique DOKTIS. DOKTIS Office et le dossier médical de DOKTIS Patient restent entièrement fonctionnels dans le code mais sont désactivés par défaut (`DOKTIS_OFFICE_ENABLED=false`) en attendant leur lancement commercial — voir [`DIRECTORY.md`](DIRECTORY.md) pour l'arborescence des routes, les flags de fonctionnalité et la procédure de déploiement.

## Architecture

- **Frontend Office** (`frontend/`) : Next.js (App Router) + TypeScript + Tailwind CSS.
- **Backend** (`backend/`) : Laravel + PostgreSQL, API REST multi-tenant (isolation par `cabinet_id` via un scope global Eloquent).
- **Authentification** : jetons Sanctum, deux types de comptes indépendants partageant le même mécanisme — `User` (personnel de cabinet) et `PatientAccount` (compte DOKTIS Patient, rattachable à un dossier médical dans plusieurs cabinets).
- **Temps réel** : Laravel Reverb (WebSockets), canaux privés par cabinet, par patient et par conversation (voir `routes/channels.php`).
- **Stockage documents** : disque local en développement, [Supabase Storage](https://supabase.com/storage) (compatible S3) en production.
- **Paiements prévus** : Mobile Money (Airtel Money, Moov Money) côté DOKTIS Patient — intégration à finaliser.

## Modules et fonctionnalités

### DOKTIS Office
Authentification et rôles (Administrateur, Médecin/Dentiste, Secrétaire, Assistant médical) · tableau de bord · dossiers patients · agenda avec détection de conflit et reprogrammation · consultations · schéma dentaire interactif (notation FDI) · documents (photos, radios, PDF) · plans de traitement · ordonnances et modèles · devis convertibles en factures · facturation avec paiement échelonné · comptabilité (dépenses, journal de caisse, export CSV) · paramètres (horaires, services/tarifs, mutuelles partenaires) · messagerie avec les patients · avis clients · tickets de support.

### DOKTIS Patient (API prête, interface à construire)
Création de compte et connexion (email ou téléphone) · recherche et prise de rendez-vous auprès d'un cabinet (le dossier médical est créé/lié automatiquement) · suivi et annulation de rendez-vous · consultation du dossier médical (ordonnances, documents, factures, plans de traitement) à travers tous les cabinets visités · messagerie sécurisée avec le cabinet · dépôt d'avis sur un cabinet.

### DOKTIS Directory (API prête, interface à construire)
Recherche publique de cabinets par ville, quartier, spécialité, langue parlée, urgences, PMR, assurance acceptée · fiche détaillée d'un cabinet (spécialités, horaires, services, avis, praticiens).

### Super Admin DOKTIS
Statistiques nationales · gestion des cabinets et de leurs abonnements · tickets de support · incidents · journal d'audit (`journal_actions`).

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

Le seeder crée un cabinet de démonstration :

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@cabinet-etoile.dokta | password |
| Médecin | medecin@cabinet-etoile.dokta | password |
| Secrétaire | secretaire@cabinet-etoile.dokta | password |
| Assistant | assistant@cabinet-etoile.dokta | password |
| Super administrateur DOKTIS | superadmin@dokta.app | password |

Ainsi qu'un compte **DOKTIS Patient** de démonstration, déjà lié à un dossier et une conversation :

| Email ou téléphone | Mot de passe |
|---|---|
| sonia@patient.dokta / 690112233 | password |

### Frontend Office (Next.js)

```bash
cd frontend
npm install
cp .env.example .env.local
npm run dev
```

L'application est disponible sur `http://localhost:3000` (API attendue sur `http://localhost:8000/api`, configurable via `NEXT_PUBLIC_API_URL`).

### Tests backend

```bash
cd backend
php artisan test
```

## Aperçu de l'API

- `POST /api/auth/register-cabinet`, `/api/auth/login` — DOKTIS Office.
- `POST /api/patient/auth/register`, `/api/patient/auth/login` — DOKTIS Patient.
- `GET /api/directory/cabinets`, `/api/directory/cabinets/{id}` — DOKTIS Directory (public).
- `GET /api/super-admin/statistiques` — Super Admin DOKTIS (rôle `super_admin`).

Le fichier `routes/api.php` regroupe l'ensemble des routes par module.

## Vision long terme

Interfaces DOKTIS Patient et DOKTIS Directory, application praticien mobile, téléconsultation, IA d'aide au diagnostic, gestion de stock (voir le cahier des charges).
