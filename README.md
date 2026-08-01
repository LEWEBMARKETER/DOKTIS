# DOKTA

Plateforme numérique de gestion des cabinets médicaux et de prise de rendez-vous, composée de trois modules interconnectés partageant les mêmes données en temps réel :

- **DOKTA Office** — le SaaS de gestion des cabinets (dossiers patients, agenda, facturation, comptabilité...).
- **DOKTA Patient** — l'application destinée aux patients (recherche de cabinet, prise de rendez-vous, dossier médical, messagerie).
- **DOKTA Directory** — l'annuaire public des professionnels de santé.

Un **Super Admin DOKTA** transverse gère les cabinets, abonnements, le support et les statistiques nationales.

Ce dépôt contient les fondations backend des trois modules (API Laravel + schéma de données) et l'application front-end de DOKTA Office (Next.js). Les interfaces DOKTA Patient et DOKTA Directory restent à construire sur cette base d'API déjà fonctionnelle.

## Architecture

- **Frontend Office** (`frontend/`) : Next.js (App Router) + TypeScript + Tailwind CSS.
- **Backend** (`backend/`) : Laravel + PostgreSQL, API REST multi-tenant (isolation par `cabinet_id` via un scope global Eloquent).
- **Authentification** : jetons Sanctum, deux types de comptes indépendants partageant le même mécanisme — `User` (personnel de cabinet) et `PatientAccount` (compte DOKTA Patient, rattachable à un dossier médical dans plusieurs cabinets).
- **Temps réel** : Laravel Reverb (WebSockets), canaux privés par cabinet, par patient et par conversation (voir `routes/channels.php`).
- **Stockage documents** : disque local en développement, [Supabase Storage](https://supabase.com/storage) (compatible S3) en production.
- **Paiements prévus** : Mobile Money (Airtel Money, Moov Money) côté DOKTA Patient — intégration à finaliser.

## Modules et fonctionnalités

### DOKTA Office
Authentification et rôles (Administrateur, Médecin/Dentiste, Secrétaire, Assistant médical) · tableau de bord · dossiers patients · agenda avec détection de conflit et reprogrammation · consultations · schéma dentaire interactif (notation FDI) · documents (photos, radios, PDF) · plans de traitement · ordonnances et modèles · devis convertibles en factures · facturation avec paiement échelonné · comptabilité (dépenses, journal de caisse, export CSV) · paramètres (horaires, services/tarifs, mutuelles partenaires) · messagerie avec les patients · avis clients · tickets de support.

### DOKTA Patient (API prête, interface à construire)
Création de compte et connexion (email ou téléphone) · recherche et prise de rendez-vous auprès d'un cabinet (le dossier médical est créé/lié automatiquement) · suivi et annulation de rendez-vous · consultation du dossier médical (ordonnances, documents, factures, plans de traitement) à travers tous les cabinets visités · messagerie sécurisée avec le cabinet · dépôt d'avis sur un cabinet.

### DOKTA Directory (API prête, interface à construire)
Recherche publique de cabinets par ville, quartier, spécialité, langue parlée, urgences, PMR, assurance acceptée · fiche détaillée d'un cabinet (spécialités, horaires, services, avis, praticiens).

### Super Admin DOKTA
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
| Super administrateur DOKTA | superadmin@dokta.app | password |

Ainsi qu'un compte **DOKTA Patient** de démonstration, déjà lié à un dossier et une conversation :

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

- `POST /api/auth/register-cabinet`, `/api/auth/login` — DOKTA Office.
- `POST /api/patient/auth/register`, `/api/patient/auth/login` — DOKTA Patient.
- `GET /api/directory/cabinets`, `/api/directory/cabinets/{id}` — DOKTA Directory (public).
- `GET /api/super-admin/statistiques` — Super Admin DOKTA (rôle `super_admin`).

Le fichier `routes/api.php` regroupe l'ensemble des routes par module.

## Déploiement en production (Railway + Vercel + Supabase)

Architecture cible : **Vercel** pour le frontend Next.js, **Railway** pour l'API Laravel (+ un service Reverb + un service queue worker), **Supabase** pour la base PostgreSQL et le stockage des documents.

### 1. Base de données et stockage (Supabase)

Déjà couvert par un projet Supabase créé au préalable. Récupérer :
- La chaîne de connexion Postgres (bouton **Connect** → onglet **Direct** → **Transaction pooler**, port 6543).
- Un bucket **Storage** privé + une clé S3-compatible (Storage → bucket → onglet **S3 Connection**).

### 2. API Laravel (Railway)

1. **New Project → Deploy from GitHub repo** → sélectionner `LEWEBMARKETER/DOKTA`, définir le **Root Directory** sur `backend`. Railway détecte Laravel automatiquement (Nixpacks) et sert l'application sans configuration supplémentaire pour le service web principal.
2. Définir les **variables d'environnement** du service (reprendre `backend/.env.example`, notamment) :
   - `APP_KEY` (générer avec `php artisan key:generate --show` en local et coller la valeur), `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=<domaine Railway du service>`
   - `DB_CONNECTION=pgsql`, `DB_HOST`, `DB_PORT=6543`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (valeurs Supabase)
   - `DOCUMENTS_DISK=supabase`, `SUPABASE_STORAGE_ENDPOINT` (endpoint S3 du bucket), `SUPABASE_STORAGE_BUCKET`, `SUPABASE_STORAGE_KEY`, `SUPABASE_STORAGE_SECRET`, `SUPABASE_STORAGE_REGION` — le disque `supabase` (voir `config/filesystems.php`) est déjà préconfiguré en S3-compatible path-style, il suffit de renseigner ces variables.
   - `BROADCAST_CONNECTION=reverb`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` (générés en local via `php artisan reverb:install` ou choisis manuellement), `REVERB_HOST=<domaine du service reverb, voir étape 4>`, `REVERB_PORT=443`, `REVERB_SCHEME=https`
   - `QUEUE_CONNECTION=database`, `SANCTUM_STATEFUL_DOMAINS=<domaine Vercel>`, `FRONTEND_URL=<domaine Vercel>`
3. Dans les réglages de déploiement du service (commande de pré-déploiement / release command), exécuter les migrations à chaque déploiement :
   ```
   php artisan migrate --force --isolated
   ```
   (`--isolated` évite une double exécution si plusieurs instances démarrent en parallèle.)

### 3. Services Reverb et queue worker (Railway)

Nixpacks ne sait servir que le HTTP entrant : les deux process suivants nécessitent chacun un **service Railway séparé**, pointé sur le **même repo/Root Directory (`backend`)** et les **mêmes variables d'environnement**, en changeant uniquement la commande de démarrage (voir `backend/Procfile` pour référence) :

- **Service `reverb`** — commande de démarrage : `php artisan reverb:start --host=0.0.0.0 --port=$PORT`. Générer un **domaine public** pour ce service spécifique : c'est cette URL/port qu'il faudra renseigner dans `REVERB_HOST` (service API) et côté frontend (`VITE_REVERB_*` / `NEXT_PUBLIC_REVERB_*`).
- **Service `worker`** — commande de démarrage : `php artisan queue:work --tries=3 --backoff=5 --sleep=3`. Pas de domaine public nécessaire.

### 4. Frontend Next.js (Vercel)

1. Importer le repo sur Vercel, **Root Directory** = `frontend`.
2. Variable d'environnement : `NEXT_PUBLIC_API_URL=<domaine Railway du service API>/api`.
   > Le frontend Next.js ne consomme pas encore Reverb (pas de client Laravel Echo intégré à ce stade) : les événements temps réel sont émis côté backend et prêts à être écoutés, mais le branchement côté UI reste à faire dans une itération suivante.
3. Une fois le domaine Vercel connu, revenir sur Railway et mettre à jour `SANCTUM_STATEFUL_DOMAINS` et `FRONTEND_URL` avec ce domaine (nécessaire pour que Sanctum accepte les requêtes du frontend en cross-domain).

### 5. Vérification post-déploiement

- `GET https://<domaine Railway>/up` doit répondre 200 (healthcheck Laravel).
- Se connecter avec un compte de démonstration (voir plus haut) depuis le frontend Vercel.
- Vérifier dans les logs du service `worker` qu'un job de broadcast s'exécute bien après une action (ex. création de rendez-vous).

## Vision long terme

Interfaces DOKTA Patient et DOKTA Directory, application praticien mobile, téléconsultation, IA d'aide au diagnostic, gestion de stock (voir le cahier des charges).
