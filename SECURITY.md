# Sécurité — DOKTIS

Ce document décrit les mécanismes de sécurité en place sur la plateforme
DOKTIS (Office, Patient, Directory), la procédure de signalement d'une
vulnérabilité, et les précautions à respecter au déploiement. Il résulte
d'un audit ciblé suivi de correctifs, menés sur la branche
`security/audit-hardening` sans réécriture de l'application existante.

## 1. Signaler une vulnérabilité

Toute découverte de faille de sécurité (accès non autorisé à des données,
contournement d'authentification, fuite de données médicales, etc.) doit
être signalée en priorité et de façon confidentielle à l'équipe technique
DOKTIS, avant toute divulgation publique. Ne pas créer d'issue publique
décrivant une vulnérabilité non corrigée. Merci d'inclure : le module
concerné (Office / Patient / Directory), les étapes de reproduction, et
l'impact estimé (quelles données, quels comptes).

## 2. Authentification

### 2.1 Mécanisme

- DOKTIS Office et DOKTIS Patient utilisent Laravel Sanctum en mode **SPA
  stateful** : authentification par **cookie de session HttpOnly**, et non
  plus par jeton Bearer stocké côté client. Deux gardes Laravel distincts et
  indépendants : `web` (personnel de cabinet, provider `users`) et `patient`
  (comptes patients, provider `patient_accounts`). Les deux sessions
  coexistent sans interférence dans le même navigateur (vérifié par test).
- Le frontend Next.js relaie `/api/*` et `/sanctum/*` vers le backend
  Laravel via `rewrites()` (`frontend/next.config.ts`), de sorte que, du
  point de vue du navigateur, l'API n'est **jamais cross-origin** — quel
  que soit le domaine réel du backend en production. Cela permet un cookie
  `SameSite=Lax` sans configuration CORS permissive.
- Plus aucun jeton d'authentification n'est stocké en `localStorage` ni
  manipulable par du JavaScript côté client. Les anciennes clés
  `dokta_token` / `dokta_patient_token` sont purgées automatiquement au
  premier chargement de l'app (migration silencieuse, aucune donnée
  sensible n'y était exposée au-delà du jeton lui-même).
- CSRF : protection native Laravel (vérification `Sec-Fetch-Site`
  same-origin + cookie `XSRF-TOKEN` à double soumission). **Jamais
  désactivée.** Le frontend obtient le cookie CSRF via
  `GET /sanctum/csrf-cookie` avant toute requête mutante (voir
  `frontend/src/lib/csrf.ts`).
- Connexion/déconnexion : `session()->regenerate()` à la connexion
  (anti-fixation de session), `session()->regenerateToken()` (pas
  `invalidate()`) à la déconnexion d'un seul garde, pour ne pas invalider
  la session de l'autre application partageant le même cookie.
- Rate limiting : les 4 routes d'authentification (connexion/inscription
  Office et Patient) sont protégées par un limiteur dédié
  (`AppServiceProvider::boot()`, `RateLimiter::for('auth', ...)`) : 20
  tentatives/minute par IP, et 5 tentatives/minute par IP + identifiant
  (email/téléphone) — limite la prise de contrôle de compte par force
  brute sans bloquer un cybercafé/cabinet partageant une IP.

### 2.2 Migration des anciennes sessions

Les anciens jetons Bearer (`dokta_token`, `dokta_patient_token`) ne sont
jamais lus ni renvoyés au serveur par le nouveau code : ils sont purgés du
navigateur au premier chargement post-déploiement. Un utilisateur
connecté au moment du déploiement devra simplement se reconnecter une
fois — aucune donnée n'est perdue, aucun jeton n'est journalisé ou
divulgué pendant cette purge.

### 2.3 Limite connue des tests (pas un comportement de production)

Certains tests PHPUnit (`PatientAuthTest`, `AuthTest`) utilisent
`app('auth')->forgetGuards()` ou appellent directement la façade
`Auth::guard(...)` plutôt que de chaîner plusieurs requêtes HTTP simulées.
Le client de test Laravel partage le même conteneur applicatif (et donc le
même cache de garde d'authentification) entre plusieurs requêtes simulées
dans un même test, ce qui n'arrive jamais en production où chaque requête
HTTP est traitée indépendamment. Ce comportement a été vérifié
manuellement en conditions réelles (navigateur réel, connexion Office puis
Patient dans le même onglet) : les deux sessions restent correctement
cloisonnées.

## 3. Isolation multi-tenant

- Architecture déjà en place avant l'audit (jugée saine) : un global scope
  Eloquent (`App\Models\Scopes\CabinetScope`) filtre automatiquement par
  `cabinet_id` de l'utilisateur connecté, via le trait
  `App\Models\Concerns\BelongsToCabinet`, utilisé par la majorité des
  modèles sensibles (Patient, Consultation, RendezVous, Ordonnance,
  Facture, Paiement, Document, PlanTraitement, Avis…). Un patient (garde
  `patient`) ne peut jamais déclencher ce scope en sa faveur (`whereRaw('1=0')`
  pour tout authentifiable autre qu'un `User`) : il doit obligatoirement
  passer par les contrôleurs `Api\Patient\*`, qui vérifient explicitement
  la propriété de la ressource.
- Le `cabinet_id`/`patient_id` envoyé par le frontend n'est **jamais fait
  confiance** : il est toujours dérivé de l'utilisateur authentifié côté
  serveur (`$request->user()->cabinet_id`), jamais lu depuis le payload
  client pour l'attribution d'une ressource.
- Étendu pendant l'audit : couverture de test explicite pour
  RendezVous, Consultation, Ordonnance, PlanTraitement, Facture (et ses
  Paiements), Avis — accès à une ressource d'un autre cabinet → `404`
  (voir `backend/tests/Feature/TenantIsolationTest.php`).
- Durci pendant l'audit : la suppression de Facture/Devis/Dépense était
  accessible à tout rôle pouvant créer ces ressources (y compris
  Secrétaire) ; réservée au rôle `administrateur`
  (`backend/routes/api.php`, groupe `role:administrateur`).

## 4. Documents médicaux

- Avant l'audit : l'API renvoyait une URL de stockage directe
  (`Storage::url()`), potentiellement non protégée selon le disque
  (disque S3/Supabase : URL publique du bucket, sans vérification
  d'autorisation à chaque accès).
- Après correctif : l'API ne renvoie plus jamais d'URL de stockage. Elle
  renvoie un chemin applicatif (`download_url`, ex.
  `/api/documents/{id}/telecharger`) qui déclenche un contrôleur
  authentifié. Celui-ci vérifie l'autorisation (même cabinet, ou patient
  propriétaire du dossier) puis diffuse le fichier via
  `Storage::disk(...)->response(...)` — le fichier ne quitte jamais le
  serveur applicatif sans passer par ce contrôle, quel que soit le disque
  configuré (local, S3, Supabase).
- Chaque téléchargement est journalisé (`document.telecharge`, voir § 6).
- Non implémenté dans ce lot, à évaluer séparément : scan antimalware à
  l'upload (dépend de l'infrastructure cible, hors périmètre « ne pas
  introduire de dépendance significative sans justification ») ; URLs
  signées à durée de vie courte pour un usage externe au domaine (non
  nécessaire ici puisque tout accès passe déjà par l'application).

## 5. Rendez-vous — concurrence et double réservation

- Avant l'audit : la vérification de disponibilité d'un créneau se faisait
  par une requête `exists()` avant insertion — fenêtre de concurrence
  classique (race condition) permettant un double-booking sous charge.
- Après correctif : contrainte **au niveau base de données** PostgreSQL
  (`EXCLUDE USING gist`, extension `btree_gist`) empêchant toute
  superposition d'intervalle `[debut, fin)` pour un même praticien parmi
  les rendez-vous non annulés — garantie vraie même sous concurrence
  réelle, contrairement à une vérification applicative. Toute violation
  renvoie une erreur PostgreSQL `23P01`, convertie en réponse HTTP `422`
  par `App\Http\Controllers\Api\Concerns\PrevientChevauchementRendezVous`.
- Validé par : un test PHPUnit à deux connexions PDO indépendantes
  simulant deux requêtes concurrentes, **et** un test manuel avec deux
  requêtes `curl` réellement concurrentes contre un serveur de
  développement multi-worker (`php artisan serve`, 4 workers) : une seule
  requête aboutit (`201`), l'autre est rejetée (`422`), une seule ligne en
  base pour le créneau.

## 6. Journal d'audit

Centralisé par `App\Models\JournalAction::enregistrer()`. Couvre :
connexion/échec de connexion/déconnexion (Office et Patient), création de
cabinet, consultation/modification/désactivation d'un dossier patient,
téléversement/téléchargement/suppression d'un document, création/
modification/suppression de facture, création/modification de rendez-vous
(y compris annulation et reprogrammation), création d'utilisateur et
changement de rôle.

Chaque entrée porte (quand pertinent) : identifiant de l'acteur, cabinet,
type d'action, type/identifiant de la ressource concernée, horodatage,
données contextuelles non sensibles. **Ne sont jamais journalisés** : mots
de passe, jetons, contenu intégral d'un diagnostic ou d'un document
médical (vérifié explicitement par test —
`JournalAuditTest::test_le_journal_ne_contient_jamais_de_mot_de_passe`).
Le journal n'est accessible qu'au rôle `super_admin` (voir
`backend/routes/api.php`) ; aucune route ne permet de le modifier après
écriture.

## 7. Fiabilité frontend

Audit des appels API mutants (`POST`/`PATCH`/`DELETE`) sur l'ensemble du
frontend : ajout systématique de `try/catch` avec message d'erreur
compréhensible affiché à l'utilisateur, et verrouillage (désactivation du
bouton / garde d'état) empêchant une double soumission pendant une
requête en cours. Concerné : messagerie patient, suppression d'un avis,
activation/désactivation/suppression d'un membre d'équipe,
confirmation/annulation d'un rendez-vous côté Office. Les formulaires de
création (patient, facture, consultation, ordonnance, rendez-vous,
inscription) disposaient déjà de ce traitement avant l'audit.

Une en-tête `Content-Security-Policy` stricte (`default-src 'self'`, pas
de script/style/police/image tiers) a été ajoutée
(`frontend/next.config.ts`), ainsi que `X-Frame-Options: DENY`,
`X-Content-Type-Options: nosniff` et un `Referrer-Policy` restrictif.
Vérifié compatible avec un build de production complet (aucune ressource
externe n'est chargée par l'application).

## 8. Dépendances

- `composer audit` (backend) : aucune vulnérabilité connue après mise à
  jour (`laravel/framework` 13.23.0 → 13.37.0, et quelques paquets
  transitifs : `league/flysystem`, `league/flysystem-aws-s3-v3`,
  `symfony/http-foundation`, `symfony/yaml`, `league/commonmark`).
- `npm audit --omit=dev` (frontend, dépendances de production) : **0
  vulnérabilité**.
- `npm audit` (incluant les dépendances de développement) : 5
  vulnérabilités « high » résiduelles, toutes dans la chaîne interne
  d'outillage ESLint (`eslint-config-next` → `@next/eslint-plugin-next` →
  `fast-glob` → `micromatch` → `braces`, déni de service par expression
  régulière/motifs imbriqués). Un correctif forcé
  (`npm audit fix --force`) rétrograderait `eslint-config-next` en
  `14.2.35`, une version majeure antérieure incompatible avec Next.js 16
  actuellement utilisé — changement de dépendance majeur non justifié par
  le risque réel (outil de lint en développement, jamais exécuté contre
  une entrée utilisateur ou en production). **Risque résiduel accepté et
  documenté ici**, à réévaluer quand une version compatible
  d'`eslint-config-next` corrigera la chaîne.

## 9. Protection des données personnelles (RGPD / cadre applicable)

**Avertissement :** ce document ne constitue pas une validation juridique.
Aucune affirmation de conformité réglementaire n'est faite ici ; une
revue par un juriste spécialisé (droit gabonais et, le cas échéant, RGPD
si des patients ou cabinets hors Gabon sont concernés) reste nécessaire
avant le lancement commercial.

Mécanismes techniques déjà en place ou renforcés par cet audit :
- Suppression douce (`SoftDeletes`) sur le modèle Patient (désactivation
  de dossier sans perte de données, pré-existante).
- Traçabilité des accès et modifications aux données médicales (journal
  d'audit, § 6).
- Isolation stricte des données entre cabinets et entre patients (§ 3).
- Accès aux documents médicaux strictement authentifié et autorisé (§ 4).

Explicitement **hors périmètre technique de cet audit**, à traiter avec
un conseil juridique avant le lancement :
- Base légale et mécanismes de consentement pour le traitement de données
  de santé (bannière, case à cocher, registre des consentements).
- Durées de conservation réglementaires des dossiers médicaux et
  mécanisme de purge/anonymisation automatique en fin de durée.
- Procédure formelle d'exercice des droits de la personne concernée
  (accès, rectification, suppression, portabilité) — actuellement
  seule une désactivation de dossier existe, pas d'export ni de
  suppression définitive à la demande du patient.
- Politique de sauvegarde (chiffrement au repos, test de restauration) et
  plan de réponse à incident (détection, notification réglementaire sous
  délai imposé le cas échéant).
- Vérification spécifique des obligations réglementaires gabonaises
  applicables aux données de santé (autorité de protection des données
  compétente, obligations de localisation des données le cas échéant) :
  **à faire valider par un juriste local**, ce rapport n'en tient pas
  lieu.

## 10. Variables d'environnement et déploiement

Nouvelles variables / changements de configuration introduits par cet
audit (voir `backend/.env.example` et `frontend/.env.example` — aucun
secret réel n'est commité, uniquement des gabarits) :

| Variable | Côté | Rôle |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | backend | Domaines autorisés à bénéficier de l'auth par cookie (déjà présent, désormais effectivement utilisé). En production : le(s) domaine(s) publics du frontend, **sans** schéma (`doktis.exemple.com`, pas `https://...`). |
| `SESSION_SECURE_COOKIE` | backend | **`true` obligatoire en production** (HTTPS) ; `false` seulement en développement local HTTP. |
| `FRONTEND_URL` / `FRONTEND_URLS` | backend | Origines autorisées par CORS (`supports_credentials` est maintenant `true` — voir `backend/config/cors.php`). Ne jamais mettre de wildcard `*` ici : avec les cookies d'identification, un wildcard serait une régression de sécurité. |
| `NEXT_PUBLIC_API_URL` | frontend | Doit rester relatif (`/api`) en production comme en développement — ne jamais pointer vers une URL absolue cross-origin, ce qui désactiverait l'authentification par cookie. |
| `BACKEND_INTERNAL_URL` | frontend | URL interne serveur-à-serveur vers Laravel, utilisée uniquement par le proxy `rewrites()` de Next.js. Jamais exposée au navigateur. En production : l'adresse privée/interne du service Laravel. |

Nouvelle migration de base de données à exécuter au déploiement :
`backend/database/migrations/2026_10_17_183711_add_overlap_exclusion_constraint_to_rendez_vous_table.php`
— active l'extension PostgreSQL `btree_gist` (nécessite les droits
`CREATE EXTENSION`, généralement déjà disponibles pour l'utilisateur
applicatif sur une base dédiée) puis ajoute une contrainte d'exclusion.
**Pré-requis avant exécution en production** : vérifier qu'aucun
rendez-vous existant en base ne viole déjà la contrainte (chevauchement
pour un même praticien) — sinon la migration échouera proprement (elle ne
corrompt aucune donnée, elle refuse simplement de s'appliquer) ; dans ce
cas, résoudre manuellement les chevauchements existants avant de
relancer la migration.

Étapes de déploiement (aucun déploiement automatique en production n'a
été effectué par cet audit) :
1. Déployer le backend, exécuter `php artisan migrate` (inclut la
   nouvelle contrainte de non-chevauchement).
2. Mettre à jour la configuration (`SANCTUM_STATEFUL_DOMAINS`,
   `SESSION_SECURE_COOKIE=true`, `FRONTEND_URL`) pour les domaines réels.
3. Déployer le frontend avec `BACKEND_INTERNAL_URL` pointant vers
   l'adresse interne du backend, et `NEXT_PUBLIC_API_URL=/api`.
4. Vérifier manuellement en environnement de pré-production : connexion
   Office, connexion Patient, déconnexion, téléchargement d'un document,
   prise de rendez-vous — avant bascule du trafic réel.

## 11. Ce que cet audit ne couvre pas

- Scan antimalware des fichiers téléversés (dépend de l'infrastructure
  cible).
- Audit de sécurité de l'infrastructure (réseau, hébergement, sauvegardes
  chiffrées) — hors périmètre code applicatif.
- Validation juridique de conformité RGPD/droit gabonais (§ 9).
- Tests de charge/déni de service applicatif.
- Interfaces DOKTIS Patient/Directory restant à construire au-delà de
  l'API existante (cf. `README.md`).
