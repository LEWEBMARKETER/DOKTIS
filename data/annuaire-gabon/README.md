# Annuaire des structures sanitaires du Gabon (DOKTIS)

Premier référentiel des structures de santé du Gabon pour **DOKTA Directory** :
pharmacies, hôpitaux, cliniques, cabinets médicaux et dentaires, laboratoires,
opticiens, kinésithérapeutes.

**Version 2026-11-21 : 115 structures**, dont 97 avec au moins un téléphone, dans 8 provinces sur 9.

| Catégorie | Nombre |
|---|---|
| Pharmacies (+ 1 dépôt) | 41 |
| Cliniques / polycliniques | 22 |
| Hôpitaux (CHU, CHR, HIA, spécialisés) | 16 |
| Cabinets dentaires | 10 |
| Cabinets médicaux | 9 |
| Opticiens | 7 |
| Laboratoires | 6 |
| Kinésithérapie | 4 |

## Fichiers prêts à l'emploi

| Fichier | Usage |
|---|---|
| `etablissements_sante_gabon.csv` | Import DOKTIS (`php artisan annuaire:importer`), Excel ou Google Sheets (UTF-8 avec BOM) |
| `etablissements_sante_gabon.json` | Front-end, application mobile, autre API |
| `etablissements_sante_gabon.sql` | Import direct dans PostgreSQL (upsert sur `code`) |

## Intégration dans DOKTIS

```bash
cd backend
php artisan migrate                 # crée la table etablissements_sante
php artisan annuaire:importer       # importe le CSV de ce dossier (idempotent)
php artisan annuaire:importer --dry-run   # valide un fichier sans écrire
```

Endpoints publics (DOKTA Directory, sans authentification) :

- `GET /api/directory/etablissements` : filtres `categorie` (une ou plusieurs, séparées par des virgules), `ville`, `province`, `quartier`, `ouvert_24h=1`, `q` (nom, praticien, quartier, spécialité), `par_page`.
- `GET /api/directory/etablissements/categories` : catégories avec leurs totaux.
- `GET /api/directory/etablissements/{id}` : fiche détaillée.

La table `etablissements_sante` est séparée de `cabinets`, qui contient les clients SaaS de DOKTA Office.
Une structure peut donc figurer dans l'annuaire sans compte DOKTIS. Quand elle revendique sa fiche,
on renseigne `cabinet_id`. Un nouvel import n'écrase jamais `statut_verification`, `verifie_le`,
`visible_annuaire` ni `cabinet_id`.

## Schéma des colonnes

| Colonne | Description |
|---|---|
| `code` | Identifiant stable, par exemple `GA-PHA-8972A3` (catégorie + empreinte du nom et de la ville). C'est la clé d'import. |
| `categorie` | `pharmacie`, `depot_pharmaceutique`, `hopital`, `clinique`, `centre_sante`, `cabinet_medical`, `cabinet_dentaire`, `laboratoire`, `imagerie`, `opticien`, `kinesitherapie`, `autre` |
| `specialites` | Liste séparée par `;` |
| `telephone` / `telephones` | Numéro principal et liste complète, au format E.164 (`+241…`) quand la conversion est sûre |
| `province` | Déduite de la ville |
| `ouvert_24h` | `oui` / `non` |
| `latitude`, `longitude`, `osm_ref` | Renseignés par l'enrichissement OpenStreetMap |
| `sources`, `source_urls` | Origine de chaque fiche, pour la traçabilité |
| `statut_verification` | `a_verifier` pour toutes les fiches de cette version |

## Méthode et limites (à lire avant mise en ligne)

- **Sources** (voir `sources.tsv`) : Le Pratique du Gabon, Gabon4you, réseau de soins Humaniis Santé,
  Miboue, Ambassade de France au Gabon, GOV.UK, annuaire UbiPharm Gabon, CNAMGS, Le Privé On line,
  Expat.com, Petit Futé, L'Union (gardes 2021), Wikipédia.
- Ces données viennent d'annuaires en ligne, parfois anciens de plusieurs années, et ont été relevées
  à partir des résultats de recherche web. **Aucune fiche n'a été vérifiée par téléphone.**
  Elles sont toutes marquées `a_verifier`. Quand les sources se contredisent, la fiche le signale dans `notes`.
- **Couverture** : le Gabon compte environ 600 établissements pharmaceutiques, dont 122 officines
  (Gabonreview), et la CNAMGS annonce plus de 150 formations sanitaires conventionnées
  (90 publiques, 63 privées) ainsi que 87 pharmacies et dépôts. Cette version est un socle,
  qui couvre surtout le Grand Libreville (89 fiches sur 115).
- Les numéros à l'ancien format mobile à 8 chiffres sont conservés tels quels, car leur conversion n'est pas certaine.
- Les noms de praticiens viennent d'annuaires professionnels publics. Avant de publier, vérifiez que
  l'affichage est conforme à la loi gabonaise n°001/2011 relative à la protection des données à caractère
  personnel (autorité de contrôle : CNPDCP) et prévoyez une procédure de retrait sur demande.

## Enrichir la base

1. **OpenStreetMap** (coordonnées GPS et centaines de structures supplémentaires) :
   ```bash
   python3 osm_import.py   # écrit donnees_osm.tsv (nécessite l'accès à overpass-api.de)
   python3 build.py        # fusionne avec la collecte manuelle et régénère CSV, JSON et SQL
   ```
   Licence ODbL : affichez « © OpenStreetMap contributors » dans DOKTA Directory.
2. **Ajouts manuels** : ajoutez une ligne dans `donnees_brutes.tsv` (séparateur tabulation, plusieurs
   téléphones séparés par ` / `, codes de sources séparés par `+`), puis lancez `python3 build.py`.
3. **Sources à exploiter en priorité** (consultation page par page nécessaire) : l'annuaire complet UbiPharm
   (une dizaine de pages de pharmacies), la liste CNAMGS des structures conventionnées, les rubriques
   santé de Le Pratique du Gabon, la liste PDF Humaniis Santé (165 entrées), GoAfrica Online.
4. **Officiel** : le registre de l'Ordre national des pharmaciens (ONPG), celui de l'Ordre des médecins
   et la carte sanitaire du ministère de la Santé restent les références à obtenir par convention.
