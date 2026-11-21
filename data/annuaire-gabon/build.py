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


if __name__ == "__main__":
    fiches = construire()
    ecrire_csv(fiches)
    ecrire_json(fiches)
    ecrire_sql(fiches)

    from collections import Counter
    print(f"{len(fiches)} structures générées")
    for cat, n in Counter(r["categorie"] for r in fiches).most_common():
        print(f"  {cat:22} {n}")
    print("Par province :", dict(Counter(r["province"] or "?" for r in fiches)))
    print("Avec téléphone :", sum(1 for r in fiches if r["telephone"]))
