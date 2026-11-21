#!/usr/bin/env python3
"""Construit l'annuaire des structures sanitaires du Gabon pour DOKTIS.

Entrées (dans ce dossier) :
  - donnees_brutes.tsv : collecte web manuelle (une ligne par structure)
  - donnees_osm.tsv    : optionnel, généré par osm_import.py (OpenStreetMap)
  - sources.tsv        : registre des sources (code -> nom, url)

Sorties :
  - etablissements_sante_gabon.csv  (UTF-8 avec BOM, séparateur virgule)
  - etablissements_sante_gabon.json
  - etablissements_sante_gabon.sql  (PostgreSQL, upsert sur `code`)
  - etablissements_sante_gabon.xlsx (classeur Excel : mode d'emploi, synthèse, annuaire, sources)

Usage : python3 build.py
"""

import csv
import hashlib
import json
import re
import unicodedata
from datetime import date
from pathlib import Path

ICI = Path(__file__).resolve().parent
DATE_COLLECTE = "2026-11-21"

CATEGORIES = {
    "pharmacie": "PHA",
    "depot_pharmaceutique": "DEP",
    "hopital": "HOP",
    "clinique": "CLI",
    "centre_sante": "CSA",
    "cabinet_medical": "MED",
    "cabinet_dentaire": "DEN",
    "laboratoire": "LAB",
    "imagerie": "IMG",
    "opticien": "OPT",
    "kinesitherapie": "KIN",
    "autre": "AUT",
}

PROVINCES = {
    "Estuaire": ["Libreville", "Owendo", "Akanda", "Ntoum", "Cocobeach", "Kango"],
    "Haut-Ogooué": ["Franceville", "Moanda", "Mounana", "Okondja", "Léconi", "Akiéni", "Bongoville", "Onga"],
    "Moyen-Ogooué": ["Lambaréné", "Ndjolé"],
    "Ngounié": ["Mouila", "Ndendé", "Fougamou", "Mimongo", "Mbigou", "Lébamba", "Mandji"],
    "Nyanga": ["Tchibanga", "Mayumba", "Moulengui-Binza"],
    "Ogooué-Ivindo": ["Makokou", "Booué", "Mékambo", "Ovan"],
    "Ogooué-Lolo": ["Koulamoutou", "Lastoursville", "Pana", "Iboundji"],
    "Ogooué-Maritime": ["Port-Gentil", "Omboué", "Gamba"],
    "Woleu-Ntem": ["Oyem", "Bitam", "Mitzic", "Minvoul", "Médouneu"],
}
VILLE_PROVINCE = {v.lower(): p for p, villes in PROVINCES.items() for v in villes}

COLONNES = [
    "code", "nom", "slug", "categorie", "specialites", "praticien", "adresse", "quartier",
    "ville", "province", "pays", "boite_postale", "telephone", "telephones", "email",
    "site_web", "horaires", "ouvert_24h", "latitude", "longitude", "osm_ref", "sources",
    "source_urls", "date_collecte", "statut_verification", "notes",
]


def sans_accents(texte: str) -> str:
    return "".join(c for c in unicodedata.normalize("NFD", texte) if unicodedata.category(c) != "Mn")


def slugifier(texte: str) -> str:
    return re.sub(r"[^a-z0-9]+", "-", sans_accents(texte).lower()).strip("-")


def cle_doublon(nom: str, ville: str) -> str:
    mots_vides = {"pharmacie", "de", "la", "le", "les", "des", "du", "d", "l", "clinique", "polyclinique", "cabinet"}
    mots = [m for m in slugifier(nom).split("-") if m and m not in mots_vides]
    return "-".join(mots) + "@" + slugifier(ville)


def normaliser_telephone(brut: str) -> str:
    """Retourne le numéro au format E.164 (+241XXXXXXXX) quand c'est sûr, sinon le brut nettoyé.

    Plan de numérotation gabonais actuel : 9 chiffres en national (011 fixe, 06x/07x mobiles),
    soit +241 suivi de 8 chiffres. L'ancien fixe à 8 chiffres « 01 xx xx xx » est devenu
    « 011 xx xx xx ». Les anciens mobiles à 8 chiffres n'ont pas de correspondance sûre :
    ils sont conservés tels quels (à vérifier).
    """
    chiffres = re.sub(r"\D", "", brut)
    if chiffres.startswith("00241"):
        chiffres = chiffres[5:]
    elif chiffres.startswith("241") and len(chiffres) >= 11:
        chiffres = chiffres[3:]
    if len(chiffres) == 9 and chiffres.startswith("0"):
        return "+241" + chiffres[1:]
    if len(chiffres) == 8 and chiffres[0] in "1267":
        if chiffres.startswith("11") or chiffres[0] in "67":
            return "+241" + chiffres
    if len(chiffres) == 8 and chiffres.startswith("01"):
        return "+24111" + chiffres[2:]
    return brut.strip()


def lire_tsv(chemin: Path) -> list[dict]:
    if not chemin.exists():
        return []
    with chemin.open(encoding="utf-8") as f:
        return list(csv.DictReader(f, delimiter="\t", quoting=csv.QUOTE_NONE))


def fusionner(base: dict, autre: dict) -> None:
    for cle, valeur in autre.items():
        if not valeur:
            continue
        if cle in ("sources", "telephones", "specialites"):
            sep = "+" if cle == "sources" else (" / " if cle == "telephones" else "; ")
            existants = [x for x in base.get(cle, "").split(sep) if x]
            for x in valeur.split(sep):
                if x and x not in existants:
                    existants.append(x)
            base[cle] = sep.join(existants)
        elif not base.get(cle):
            base[cle] = valeur


def construire() -> list[dict]:
    sources = {s["code"]: s for s in lire_tsv(ICI / "sources.tsv")}
    sources.setdefault("OSM", {
        "code": "OSM", "nom": "OpenStreetMap (ODbL)", "url": "https://www.openstreetmap.org/copyright",
    })

    fiches: dict[str, dict] = {}
    for ligne in lire_tsv(ICI / "donnees_brutes.tsv") + lire_tsv(ICI / "donnees_osm.tsv"):
        ligne = {k: (v or "").strip() for k, v in ligne.items()}
        if not ligne.get("nom") or not ligne.get("ville"):
            continue
        cle = cle_doublon(ligne["nom"], ligne["ville"])
        if cle in fiches:
            fusionner(fiches[cle], ligne)
        else:
            fiches[cle] = ligne

    resultat = []
    for ligne in fiches.values():
        categorie = ligne["categorie"]
        if categorie not in CATEGORIES:
            raise ValueError(f"Catégorie inconnue « {categorie} » pour {ligne['nom']}")

        slug = slugifier(f"{ligne['nom']} {ligne['ville']}")
        code = f"GA-{CATEGORIES[categorie]}-{hashlib.sha1(slug.encode()).hexdigest()[:6].upper()}"

        telephones = []
        for brut in re.split(r"\s*/\s*", ligne.get("telephones", "")):
            if brut and (tel := normaliser_telephone(brut)) not in telephones:
                telephones.append(tel)

        codes_sources = [c for c in ligne.get("sources", "").split("+") if c]
        inconnues = [c for c in codes_sources if c not in sources]
        if inconnues:
            raise ValueError(f"Source(s) inconnue(s) {inconnues} pour {ligne['nom']}")
        urls = [sources[c]["url"] for c in codes_sources]
        if ligne.get("osm_ref"):
            urls.append("https://www.openstreetmap.org/" + ligne["osm_ref"])

        ville = ligne["ville"]
        resultat.append({
            "code": code,
            "nom": ligne["nom"],
            "slug": slug,
            "categorie": categorie,
            "specialites": ligne.get("specialites", ""),
            "praticien": ligne.get("praticien", ""),
            "adresse": ligne.get("adresse", ""),
            "quartier": ligne.get("quartier", ""),
            "ville": ville,
            "province": VILLE_PROVINCE.get(ville.lower(), ""),
            "pays": "Gabon",
            "boite_postale": ligne.get("boite_postale", ""),
            "telephone": telephones[0] if telephones else "",
            "telephones": "; ".join(telephones),
            "email": ligne.get("email", ""),
            "site_web": ligne.get("site_web", ""),
            "horaires": ligne.get("horaires", ""),
            "ouvert_24h": "oui" if ligne.get("ouvert_24h") == "oui" else "non",
            "latitude": ligne.get("latitude", ""),
            "longitude": ligne.get("longitude", ""),
            "osm_ref": ligne.get("osm_ref", ""),
            "sources": "; ".join(sources[c]["nom"] for c in codes_sources),
            "source_urls": "; ".join(urls),
            "date_collecte": DATE_COLLECTE,
            "statut_verification": "a_verifier",
            "notes": ligne.get("notes", ""),
        })

    ordre = list(CATEGORIES)
    resultat.sort(key=lambda r: (ordre.index(r["categorie"]), r["province"], r["ville"], slugifier(r["nom"])))

    codes = [r["code"] for r in resultat]
    assert len(codes) == len(set(codes)), "Collision de codes"
    return resultat


def ecrire_csv(fiches: list[dict]) -> None:
    with (ICI / "etablissements_sante_gabon.csv").open("w", encoding="utf-8-sig", newline="") as f:
        writer = csv.DictWriter(f, fieldnames=COLONNES)
        writer.writeheader()
        writer.writerows(fiches)


def ecrire_json(fiches: list[dict]) -> None:
    def convertir(r: dict) -> dict:
        r = dict(r)
        r["specialites"] = [s.strip() for s in r["specialites"].split(";") if s.strip()]
        r["telephones"] = [t.strip() for t in r["telephones"].split(";") if t.strip()]
        r["sources"] = [s.strip() for s in r["sources"].split(";") if s.strip()]
        r["source_urls"] = [s.strip() for s in r["source_urls"].split(";") if s.strip()]
        r["ouvert_24h"] = r["ouvert_24h"] == "oui"
        r["latitude"] = float(r["latitude"]) if r["latitude"] else None
        r["longitude"] = float(r["longitude"]) if r["longitude"] else None
        return {k: (v if v != "" else None) for k, v in r.items()}

    contenu = {
        "nom": "Annuaire DOKTIS des structures sanitaires du Gabon",
        "version": DATE_COLLECTE,
        "genere_le": date.today().isoformat(),
        "licence_note": "Compilation de sources publiques citées par fiche ; données OpenStreetMap sous licence ODbL.",
        "total": len(fiches),
        "etablissements": [convertir(r) for r in fiches],
    }
    (ICI / "etablissements_sante_gabon.json").write_text(
        json.dumps(contenu, ensure_ascii=False, indent=2) + "\n", encoding="utf-8"
    )


def ecrire_sql(fiches: list[dict]) -> None:
    def q(valeur: str) -> str:
        return "NULL" if valeur == "" else "'" + valeur.replace("'", "''") + "'"

    def q_json_liste(valeur: str) -> str:
        items = [s.strip() for s in valeur.split(";") if s.strip()]
        return "NULL" if not items else q(json.dumps(items, ensure_ascii=False))

    colonnes = [
        "code", "nom", "slug", "categorie", "specialites", "praticien", "adresse", "quartier",
        "ville", "province", "pays", "boite_postale", "telephone", "telephones", "email",
        "site_web", "horaires", "ouvert_24h", "latitude", "longitude", "osm_ref", "sources",
        "source_urls", "statut_verification", "notes", "visible_annuaire", "created_at", "updated_at",
    ]
    lignes = [
        "-- Annuaire DOKTIS des structures sanitaires du Gabon",
        f"-- Version {DATE_COLLECTE} – {len(fiches)} structures. Généré par data/annuaire-gabon/build.py",
        "-- Prérequis : migration create_etablissements_sante_table (php artisan migrate).",
        "BEGIN;",
    ]
    for r in fiches:
        valeurs = [
            q(r["code"]), q(r["nom"]), q(r["slug"]), q(r["categorie"]), q_json_liste(r["specialites"]),
            q(r["praticien"]), q(r["adresse"]), q(r["quartier"]), q(r["ville"]), q(r["province"]),
            q(r["pays"]), q(r["boite_postale"]), q(r["telephone"]), q_json_liste(r["telephones"]),
            q(r["email"]), q(r["site_web"]), q(r["horaires"]),
            "TRUE" if r["ouvert_24h"] == "oui" else "FALSE",
            r["latitude"] or "NULL", r["longitude"] or "NULL", q(r["osm_ref"]),
            q_json_liste(r["sources"]), q_json_liste(r["source_urls"]),
            q(r["statut_verification"]), q(r["notes"]), "TRUE", "NOW()", "NOW()",
        ]
        maj = ", ".join(
            f"{c} = EXCLUDED.{c}" for c in colonnes
            if c not in ("code", "created_at", "statut_verification", "visible_annuaire")
        )
        lignes.append(
            f"INSERT INTO etablissements_sante ({', '.join(colonnes)}) VALUES ({', '.join(valeurs)}) "
            f"ON CONFLICT (code) DO UPDATE SET {maj};"
        )
    lignes.append("COMMIT;")
    (ICI / "etablissements_sante_gabon.sql").write_text("\n".join(lignes) + "\n", encoding="utf-8")


LIBELLES = {
    "pharmacie": "Pharmacie", "depot_pharmaceutique": "Dépôt pharmaceutique", "hopital": "Hôpital",
    "clinique": "Clinique / Polyclinique", "centre_sante": "Centre de santé", "cabinet_medical": "Cabinet médical",
    "cabinet_dentaire": "Cabinet dentaire", "laboratoire": "Laboratoire d'analyses", "imagerie": "Imagerie médicale",
    "opticien": "Opticien", "kinesitherapie": "Kinésithérapie", "autre": "Autre professionnel de santé",
}

DESCRIPTIONS = {
    "code": "Identifiant stable (clé d'import DOKTIS). Ne pas modifier.",
    "nom": "Nom de la structure.",
    "slug": "Identifiant URL (généré).",
    "categorie": "Code catégorie (liste déroulante, voir onglet Synthèse).",
    "specialites": "Spécialités / services, séparés par « ; ».",
    "praticien": "Praticien principal, si connu.",
    "adresse": "Adresse ou repère.",
    "quartier": "Quartier.",
    "ville": "Ville / commune.",
    "province": "Province (déduite de la ville).",
    "pays": "Pays.",
    "boite_postale": "Boîte postale.",
    "telephone": "Téléphone principal (format international +241…).",
    "telephones": "Tous les téléphones, séparés par « ; ».",
    "email": "Adresse e-mail.",
    "site_web": "Site web.",
    "horaires": "Horaires d'ouverture.",
    "ouvert_24h": "oui / non.",
    "latitude": "Latitude GPS (décimale).",
    "longitude": "Longitude GPS (décimale).",
    "osm_ref": "Référence OpenStreetMap (type/id).",
    "sources": "Sources de la fiche, séparées par « ; ».",
    "source_urls": "Liens vers les sources.",
    "date_collecte": "Date de collecte (AAAA-MM-JJ).",
    "statut_verification": "a_verifier / verifie / ferme (liste déroulante).",
    "notes": "Remarques : contradictions entre sources, informations à compléter.",
}


def ecrire_xlsx(fiches: list[dict]) -> None:
    from openpyxl import Workbook
    from openpyxl.comments import Comment
    from openpyxl.styles import Alignment, Border, Font, PatternFill, Side
    from openpyxl.utils import get_column_letter
    from openpyxl.worksheet.datavalidation import DataValidation
    from openpyxl.worksheet.table import Table, TableStyleInfo

    police = "Arial"
    entete_font = Font(name=police, bold=True, color="FFFFFF", size=10)
    entete_fill = PatternFill("solid", start_color="0B6E4F")
    normal = Font(name=police, size=10)
    gras = Font(name=police, size=10, bold=True)
    titre = Font(name=police, size=14, bold=True, color="0B6E4F")
    bordure = Border(*(Side(style="thin", color="D9D9D9"),) * 4)
    jaune = PatternFill("solid", start_color="FFFF00")

    wb = Workbook()

    # --- Onglet Annuaire (mêmes colonnes que le CSV d'import) ---
    ws = wb.active
    ws.title = "Annuaire"
    ws.append(COLONNES)
    for r in fiches:
        ligne = []
        for c in COLONNES:
            v = r[c]
            if c in ("latitude", "longitude") and v:
                v = float(v)
            ligne.append(v if v != "" else None)
        ws.append(ligne)

    derniere = ws.max_row
    largeurs = {"code": 15, "nom": 42, "slug": 30, "categorie": 20, "specialites": 34, "praticien": 22,
                "adresse": 40, "quartier": 18, "ville": 14, "province": 16, "pays": 8, "boite_postale": 10,
                "telephone": 16, "telephones": 38, "email": 28, "site_web": 28, "horaires": 26,
                "ouvert_24h": 10, "latitude": 11, "longitude": 11, "osm_ref": 14, "sources": 40,
                "source_urls": 50, "date_collecte": 12, "statut_verification": 16, "notes": 60}
    for i, c in enumerate(COLONNES, start=1):
        lettre = get_column_letter(i)
        ws.column_dimensions[lettre].width = largeurs.get(c, 15)
        cellule = ws.cell(row=1, column=i)
        cellule.font, cellule.fill = entete_font, entete_fill
        cellule.alignment = Alignment(vertical="center", wrap_text=True)
        cellule.comment = Comment(DESCRIPTIONS[c], "DOKTIS")
    for ligne in ws.iter_rows(min_row=2, max_row=derniere):
        for cellule in ligne:
            cellule.font = normal
            cellule.border = bordure
            cellule.alignment = Alignment(vertical="top")
    ws.freeze_panes = "C2"
    ws.row_dimensions[1].height = 30

    table = Table(displayName="Annuaire", ref=f"A1:{get_column_letter(len(COLONNES))}{derniere}")
    table.tableStyleInfo = TableStyleInfo(name="TableStyleLight9", showRowStripes=True)
    ws.add_table(table)

    col = {c: get_column_letter(i) for i, c in enumerate(COLONNES, start=1)}
    dv_cat = DataValidation(type="list", formula1='"' + ",".join(CATEGORIES) + '"', allow_blank=False)
    dv_statut = DataValidation(type="list", formula1='"a_verifier,verifie,ferme"', allow_blank=False)
    dv_24h = DataValidation(type="list", formula1='"oui,non"', allow_blank=False)
    for dv, c in ((dv_cat, "categorie"), (dv_statut, "statut_verification"), (dv_24h, "ouvert_24h")):
        ws.add_data_validation(dv)
        dv.add(f"{col[c]}2:{col[c]}5000")

    # --- Onglet Synthèse (formules) ---
    syn = wb.create_sheet("Synthèse")
    provinces = list(PROVINCES)
    syn["A1"] = "Annuaire DOKTIS des structures sanitaires du Gabon : synthèse"
    syn["A1"].font = titre
    syn["A2"] = f"Version {DATE_COLLECTE}. Totaux calculés par formule à partir de l'onglet Annuaire."
    syn["A2"].font = Font(name=police, size=9, italic=True, color="595959")

    rng = lambda c: f"Annuaire!${col[c]}$2:${col[c]}$5000"
    entetes = ["Catégorie", "Code"] + provinces + ["Total", "Avec téléphone", "Ouvert 24h/24"]
    ligne_entete = 4
    for j, e in enumerate(entetes, start=1):
        c = syn.cell(row=ligne_entete, column=j, value=e)
        c.font, c.fill = entete_font, entete_fill
        c.alignment = Alignment(horizontal="center", vertical="center", wrap_text=True)
    syn.row_dimensions[ligne_entete].height = 32

    premiere = ligne_entete + 1
    for i, code in enumerate(CATEGORIES):
        r = premiere + i
        syn.cell(row=r, column=1, value=LIBELLES[code])
        syn.cell(row=r, column=2, value=code)
        for j, _ in enumerate(provinces, start=3):
            entete_prov = f"{get_column_letter(j)}${ligne_entete}"
            syn.cell(row=r, column=j, value=f'=COUNTIFS({rng("categorie")},$B{r},{rng("province")},{entete_prov})')
        jt = 3 + len(provinces)
        syn.cell(row=r, column=jt, value=f'=COUNTIF({rng("categorie")},$B{r})')
        syn.cell(row=r, column=jt + 1, value=f'=COUNTIFS({rng("categorie")},$B{r},{rng("telephone")},"<>")')
        syn.cell(row=r, column=jt + 2, value=f'=COUNTIFS({rng("categorie")},$B{r},{rng("ouvert_24h")},"oui")')
    der = premiere + len(CATEGORIES) - 1
    rt = der + 1
    syn.cell(row=rt, column=1, value="Total")
    for j in range(3, len(entetes) + 1):
        lettre = get_column_letter(j)
        syn.cell(row=rt, column=j, value=f"=SUM({lettre}{premiere}:{lettre}{der})")
    for ligne in syn.iter_rows(min_row=premiere, max_row=rt, max_col=len(entetes)):
        for c in ligne:
            c.font = gras if c.row == rt else normal
            c.border = bordure
            if c.column >= 3:
                c.number_format = "#,##0;-#,##0;-"
                c.alignment = Alignment(horizontal="center")
    for c in syn[rt]:
        c.fill = PatternFill("solid", start_color="E2EFDA")

    rc = rt + 2
    syn.cell(row=rc, column=1, value="Contrôle : lignes de l'onglet Annuaire").font = normal
    syn.cell(row=rc, column=2, value=f'=COUNTA({rng("code")})').font = normal
    syn.cell(row=rc + 1, column=1, value="Fiches à vérifier").font = normal
    syn.cell(row=rc + 1, column=2, value=f'=COUNTIF({rng("statut_verification")},"a_verifier")').font = normal
    syn.cell(row=rc + 2, column=1, value="Fiches vérifiées").font = normal
    syn.cell(row=rc + 2, column=2, value=f'=COUNTIF({rng("statut_verification")},"verifie")').font = normal
    syn.column_dimensions["A"].width = 38
    syn.column_dimensions["B"].width = 20
    for j in range(3, len(entetes) + 1):
        syn.column_dimensions[get_column_letter(j)].width = 13
    syn.freeze_panes = f"C{premiere}"

    # --- Onglet Sources ---
    src = wb.create_sheet("Sources")
    src.append(["Code", "Source", "URL"])
    for s in lire_tsv(ICI / "sources.tsv"):
        src.append([s["code"], s["nom"], s["url"]])
    src.append(["OSM", "OpenStreetMap (ODbL), pour les futurs enrichissements", "https://www.openstreetmap.org/copyright"])
    for c in src[1]:
        c.font, c.fill = entete_font, entete_fill
    for ligne in src.iter_rows(min_row=2):
        for c in ligne:
            c.font = normal
        ligne[2].hyperlink = ligne[2].value
        ligne[2].font = Font(name=police, size=10, color="0563C1", underline="single")
    src.column_dimensions["A"].width = 10
    src.column_dimensions["B"].width = 45
    src.column_dimensions["C"].width = 95

    # --- Onglet Mode d'emploi ---
    aide = wb.create_sheet("Mode d'emploi")
    lignes = [
        ("Annuaire DOKTIS des structures sanitaires du Gabon", titre),
        (f"Version {DATE_COLLECTE} : {len(fiches)} structures issues de sources web publiques (voir onglet Sources).", normal),
        ("", normal),
        ("Avertissement", gras),
        ("Aucune fiche n'a encore été vérifiée par téléphone : toutes sont au statut « a_verifier ».", normal),
        ("Certaines sources datent de plusieurs années. Les contradictions entre sources sont signalées dans la colonne « notes ».", normal),
        ("", normal),
        ("Comment mettre à jour", gras),
        ("1. Modifiez ou complétez les fiches dans l'onglet Annuaire (une ligne = une structure).", normal),
        ("2. Après vérification par téléphone, passez « statut_verification » à « verifie » (ou « ferme » si la structure n'existe plus).", normal),
        ("3. Pour une nouvelle structure, laissez « code » et « slug » vides : ils sont générés par build.py.", normal),
        ("   Le plus simple est d'ajouter la ligne dans data/annuaire-gabon/donnees_brutes.tsv, puis de lancer python3 build.py.", normal),
        ("4. Plusieurs valeurs dans une même cellule (téléphones, spécialités, sources) se séparent par « ; ».", normal),
        ("5. Les listes déroulantes contrôlent les colonnes categorie, ouvert_24h et statut_verification.", normal),
        ("", normal),
        ("Import dans DOKTIS", gras),
        ("Enregistrez l'onglet Annuaire au format « CSV UTF-8 (délimité par des virgules) », puis :", normal),
        ("php artisan annuaire:importer chemin/vers/fichier.csv", Font(name="Courier New", size=10)),
        ("L'import est idempotent (clé : code) et n'écrase jamais le statut de vérification déjà saisi dans DOKTIS.", normal),
        ("", normal),
        ("Exemple de ligne bien remplie", gras),
    ]
    for texte, f in lignes:
        aide.append([texte])
        aide.cell(row=aide.max_row, column=1).font = f
    exemple = next(r for r in fiches if r["nom"] == "Grande Pharmacie des Forestiers")
    for c in ("nom", "categorie", "adresse", "quartier", "ville", "province", "telephone", "telephones",
              "email", "ouvert_24h", "sources", "statut_verification"):
        aide.append([c, exemple[c]])
        aide.cell(row=aide.max_row, column=1).font = gras
        aide.cell(row=aide.max_row, column=2).font = normal
    aide.append([])
    aide.append(["Description des colonnes"])
    aide.cell(row=aide.max_row, column=1).font = gras
    for c in COLONNES:
        aide.append([c, DESCRIPTIONS[c]])
        aide.cell(row=aide.max_row, column=1).font = gras
        aide.cell(row=aide.max_row, column=2).font = normal
    aide.column_dimensions["A"].width = 24
    aide.column_dimensions["B"].width = 90

    wb.move_sheet("Mode d'emploi", offset=-3)
    wb.move_sheet("Synthèse", offset=-1)
    wb.active = 0
    wb.save(ICI / "etablissements_sante_gabon.xlsx")


if __name__ == "__main__":
    fiches = construire()
    ecrire_csv(fiches)
    ecrire_json(fiches)
    ecrire_sql(fiches)
    ecrire_xlsx(fiches)

    from collections import Counter
    print(f"{len(fiches)} structures générées")
    for cat, n in Counter(r["categorie"] for r in fiches).most_common():
        print(f"  {cat:22} {n}")
    print("Par province :", dict(Counter(r["province"] or "?" for r in fiches)))
    print("Avec téléphone :", sum(1 for r in fiches if r["telephone"]))
