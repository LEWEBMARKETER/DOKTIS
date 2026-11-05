# Mettre DOKTIS en ligne sur Render

Le fichier [`render.yaml`](../render.yaml) décrit toute l'infrastructure. Render la crée en une fois :

| Élément | Produit Render | Rôle |
|---|---|---|
| `doktis-db` | Postgres (`free`) | Base de données, accessible uniquement par le réseau privé |
| `doktis-api` | Service web Docker (`free`) | API Laravel (PHP 8.4), dossier `backend/` |
| `doktis-web` | Service web Node (`free`) | Site public, espace praticien et Super Admin (Next.js), dossier `frontend/` |

Tout est à **Frankfurt**, la région Render la plus proche du Gabon.

Le navigateur n'appelle que le site : les requêtes `/api/...` sont relayées par Next.js vers l'API. Il n'y a donc pas de CORS à configurer.

### Version gratuite (actuelle) : à savoir
- Les services gratuits **s'endorment après environ 15 minutes sans visite** : la page suivante met de longues secondes à s'afficher.
- La **base gratuite expire au bout d'environ un mois**, avec ses données. Passer aux offres payantes avant d'y saisir de vraies fiches.
- Un service gratuit ne reçoit pas de trafic par le réseau privé : le site joint donc l'API par son **adresse publique**, réglée dans `render.yaml` (`API_ORIGIN` = `https://doktis-api.onrender.com`). Si Render attribue une autre adresse au service `doktis-api` (nom déjà pris), corriger `API_ORIGIN` dans les variables de `doktis-web` puis relancer un déploiement du site.
- La limitation du nombre de tentatives peut être moins précise qu'en version payante, selon la façon dont Render transmet l'adresse du visiteur.

### Passer aux offres payantes (production)
Environ 20 $/mois au total, à vérifier sur la page Tarification de Render. Dans `render.yaml` :
1. `plan: basic-256mb` pour `doktis-db`, `plan: starter` pour `doktis-api` et `doktis-web` ;
2. dans `doktis-web`, remplacer `API_ORIGIN` par l'adresse privée de l'API :
   ```yaml
   - key: API_HOSTPORT
     fromService:
       type: web
       name: doktis-api
       property: hostport
   ```
Une base gratuite ne se transforme pas toujours en base payante en gardant ses données : faire la bascule avant de saisir de vraies fiches.

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

## 5. Aperçu avec des données de démonstration

Tant que `DOKTA_DONNEES_DEMO` vaut `true` (réglage actuel de `render.yaml`), l'API charge au démarrage 8 cabinets fictifs à Libreville, Owendo, Port-Gentil et Franceville : horaires, tarifs, praticiens, et créneaux de la semaine à venir pour le Cabinet Dentaire Étoile. Chaque fiche porte la mention « Fiche de démonstration DOKTIS : ce cabinet n'existe pas ». Les créneaux sont renouvelés à chaque redémarrage de l'API s'il n'en reste plus.

Deux réglages facultatifs, dans `doktis-api` → **Environment** :

| Variable | Effet |
|---|---|
| `DOKTA_DEMO_WHATSAPP` | Votre numéro (ex. `077 12 34 56`) : le bouton WhatsApp des fiches de démo vous envoie les demandes. Sans lui, les fiches de démo n'ont pas de contact (aucun numéro inventé, pour ne pas écrire à des inconnus). |
| `DOKTA_DEMO_PASSWORD` | Active la connexion praticien de démo : `praticien.etoile@demo.doktis` avec ce mot de passe, pour voir **Ma fiche**, **Mes créneaux** et **Partager ma page**. |

Après un changement de variable, Render redémarre l'API. Les pages publiques se mettent à jour en 5 minutes au plus.

Avant de saisir de vraies fiches : passer `DOKTA_DONNEES_DEMO` à `false` et repartir d'une base neuve (c'est le cas en passant aux offres payantes avec une nouvelle base). Les fiches de démo sont repérables par leur plan `demo`.

## 6. Remplir l'annuaire

Avec le compte Super Admin : **Fiches annuaire** → **Nouvelle fiche** pour chaque cabinet de la ville pilote. Les praticiens revendiquent ensuite leur fiche depuis le site ; les demandes arrivent dans **Revendications**.

## En cas de problème

- **Journaux** : chaque service a un onglet **Logs** (l'API y écrit ses erreurs, en clair).
- **L'API ne démarre pas** : vérifier dans ses journaux le message affiché avant l'arrêt (connexion à la base, variable manquante).
- **Le site affiche des pages vides** : vérifier que `doktis-api` est en état *Live*, puis ouvrir `/api/directory/specialites` sur le site. Les pages publiques se régénèrent toutes les 5 minutes.
- **Le site ne joint pas l'API** (pages vides, erreurs sur `/api/...`) : vérifier que `API_ORIGIN` de `doktis-web` correspond bien à l'adresse affichée par Render pour `doktis-api`, puis redéployer le site.
- **Première visite très lente** : normal en version gratuite (réveil des services).

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
