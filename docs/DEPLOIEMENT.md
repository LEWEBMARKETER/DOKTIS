# Mettre DOKTIS en ligne sur Render

Le fichier [`render.yaml`](../render.yaml) décrit toute l'infrastructure. Render la crée en une fois :

| Élément | Produit Render | Rôle |
|---|---|---|
| `doktis-db` | Postgres (`basic-256mb`) | Base de données, accessible uniquement par le réseau privé |
| `doktis-api` | Service web Docker (`starter`) | API Laravel (PHP 8.4), dossier `backend/` |
| `doktis-web` | Service web Node (`starter`) | Site public, espace praticien et Super Admin (Next.js), dossier `frontend/` |

Tout est à **Frankfurt**, la région Render la plus proche du Gabon. Coût indicatif : environ 20 $/mois (deux services web d'entrée de gamme et une petite base) ; à vérifier sur la page Tarification de Render.

Le navigateur n'appelle que le site : les requêtes `/api/...` sont relayées par Next.js vers l'API sur le réseau privé de Render. Il n'y a donc ni CORS ni adresse d'API à configurer.

## 1. Préparer le dépôt

Render déploie une branche GitHub. Deux possibilités :
- fusionner la branche de travail dans `main` (recommandé), puis déployer `main` ;
- ou choisir directement la branche `claude/exciting-planck-5bp5dr` à l'étape 2.

Chaque nouveau commit sur la branche choisie redéploie automatiquement le service concerné (l'API seulement si `backend/` change, le site seulement si `frontend/` change).

## 2. Créer le blueprint

1. Sur [dashboard.render.com](https://dashboard.render.com) : **New** → **Blueprint**.
2. Connecter GitHub si ce n'est pas déjà fait, puis choisir le dépôt `lewebmarketer/doktis` et la branche.
3. Render lit `render.yaml` et affiche les trois éléments à créer.
4. Renseigner les deux valeurs demandées :
   - `DOKTA_SUPERADMIN_EMAIL` : l'e-mail du premier compte Super Admin ;
   - `DOKTA_SUPERADMIN_PASSWORD` : un mot de passe long (16 caractères ou plus).
5. Cliquer sur **Apply**. La base est créée, puis l'API, puis le site. Le premier déploiement prend 5 à 10 minutes.

Au démarrage, l'API applique automatiquement les migrations, crée les spécialités et les assureurs (CNAMGS, NSIA, Ogar, ASCOMA), puis le compte Super Admin s'il n'existe pas encore. Aucune donnée de démonstration n'est créée en production.

## 3. Vérifier

Remplacer `doktis-web.onrender.com` par l'adresse affichée par Render pour le service `doktis-web` :

- `https://doktis-web.onrender.com` : la page d'accueil s'affiche (vide tant qu'aucune fiche n'est créée) ;
- `https://doktis-web.onrender.com/api/directory/specialites` : la liste des spécialités en JSON ;
- `https://doktis-web.onrender.com/login` : se connecter avec le compte Super Admin, puis ouvrir **Fiches annuaire** ;
- `https://doktis-web.onrender.com/sitemap.xml` : les adresses doivent commencer par celle du site (et non `localhost`).

Une fois connecté, vous pouvez supprimer `DOKTA_SUPERADMIN_PASSWORD` des variables de `doktis-api` : le compte existe déjà et son mot de passe ne sera plus jamais modifié par le déploiement.

## 4. Brancher le nom de domaine

1. Service `doktis-web` → **Settings** → **Custom Domains** → ajouter `doktis.ga` (ou le domaine choisi) et `www.doktis.ga`.
2. Créer chez le registraire les enregistrements DNS indiqués par Render. Le certificat HTTPS est fourni automatiquement.
3. Service `doktis-web` → **Environment** → ajouter `NEXT_PUBLIC_SITE_URL` = `https://doktis.ga`, puis **Manual Deploy** → **Clear build cache & deploy**. Cette adresse sert au plan du site, aux liens canoniques et aux QR codes.

L'API n'a pas besoin de domaine : elle reste derrière le site.

## 5. Remplir l'annuaire

Avec le compte Super Admin : **Fiches annuaire** → **Nouvelle fiche** pour chaque cabinet de la ville pilote. Les praticiens revendiquent ensuite leur fiche depuis le site ; les demandes arrivent dans **Revendications**.

## En cas de problème

- **Journaux** : chaque service a un onglet **Logs** (l'API y écrit ses erreurs, en clair).
- **L'API ne démarre pas** : vérifier dans ses journaux le message affiché avant l'arrêt (connexion à la base, variable manquante).
- **Le site affiche des pages vides** : vérifier que `doktis-api` est en état *Live*, puis ouvrir `/api/directory/specialites` sur le site. Les pages publiques se régénèrent toutes les 5 minutes.
- **`API_HOSTPORT` refusé à l'import** : remplacer cette variable par `API_ORIGIN` = `http://doktis-api:10000` (adresse privée de l'API).

## Tester l'image de l'API en local

```bash
cd backend
docker build -t doktis-api .
docker run --rm -p 8080:10000 \
  -e APP_KEY_SEED=une-valeur-aleatoire -e APP_ENV=production -e APP_DEBUG=false \
  -e LOG_CHANNEL=stderr -e SESSION_DRIVER=array -e CACHE_STORE=database \
  -e DB_URL=postgresql://utilisateur:motdepasse@hote:5432/base \
  doktis-api
```
