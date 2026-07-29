# DOKTA

Micro SaaS de gestion de cabinets dentaires et médicaux (MVP), conforme au cahier des charges fonctionnel : dossiers patients, agenda, consultations, facturation, documents et pilotage d'activité, sur une architecture multi-cabinets (multi-tenant).

## Architecture

- **Frontend** (`frontend/`) : Next.js (App Router) + TypeScript + Tailwind CSS, application cliente consommant l'API REST.
- **Backend** (`backend/`) : Laravel + PostgreSQL, API REST authentifiée par jetons Sanctum, multi-tenant par `cabinet_id` (isolation au niveau des lignes via un scope global).
- **Stockage documents** : disque local en développement, [Supabase Storage](https://supabase.com/storage) (compatible S3) en production.

## Modules couverts

- Authentification et gestion des rôles (Administrateur, Médecin/Dentiste, Secrétaire, Assistant médical, Super administrateur DOKTA)
- Tableau de bord (statistiques d'activité et de facturation)
- Gestion des patients (dossier centralisé, historique médical)
- Agenda / rendez-vous avec détection de conflit de créneau
- Consultations et historique médical
- Documents patients (photos, radios, PDF) avec suivi photographique
- Plans de traitement avec étapes
- Ordonnances et modèles d'ordonnances réutilisables
- Facturation avec paiement échelonné (multi-échéances)
- Administration multi-cabinets (réservée au super administrateur)

## Démarrage rapide

### Backend (Laravel)

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate
# Configurer une base PostgreSQL puis :
php artisan migrate --seed
php artisan serve
```

Le seeder crée un cabinet de démonstration :

| Rôle | Email | Mot de passe |
|---|---|---|
| Administrateur | admin@cabinet-etoile.dokta | password |
| Médecin | medecin@cabinet-etoile.dokta | password |
| Secrétaire | secretaire@cabinet-etoile.dokta | password |
| Assistant | assistant@cabinet-etoile.dokta | password |
| Super administrateur DOKTA | superadmin@dokta.app | password |

### Frontend (Next.js)

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

## Vision long terme

Extension vers une application patient, une application praticien, la téléconsultation, l'IA d'aide au diagnostic, la gestion de stock et l'intégration des assurances (voir le cahier des charges).
