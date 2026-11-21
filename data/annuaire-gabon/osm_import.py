#!/usr/bin/env python3
"""Télécharge les structures de santé du Gabon depuis OpenStreetMap (API Overpass)
et écrit donnees_osm.tsv, au même format que donnees_brutes.tsv (+ latitude,
longitude, osm_ref). Relancer ensuite build.py, qui fusionne les doublons
(même nom et même ville) et ajoute les coordonnées GPS aux fiches existantes.

Usage : python3 osm_import.py [--overpass https://overpass-api.de/api/interpreter]

Données OpenStreetMap © contributeurs OSM, licence ODbL : l'attribution
« © OpenStreetMap contributors » doit apparaître dans DOKTA Directory.
"""

import argparse
import json
import urllib.parse
import urllib.request
from pathlib import Path

ICI = Path(__file__).resolve().parent

REQUETE = """
[out:json][timeout:170];
area["ISO3166-1"="GA"][admin_level=2]->.gabon;
(
  nwr["amenity"~"^(pharmacy|clinic|doctors|dentist|hospital)$"](area.gabon);
  nwr["healthcare"](area.gabon);
  nwr["shop"="optician"](area.gabon);
);
out center tags;
"""

CATEGORIE_OSM = {
    "pharmacy": "pharmacie",
    "hospital": "hopital",
    "clinic": "clinique",
    "doctors": "cabinet_medical",
    "dentist": "cabinet_dentaire",
    "doctor": "cabinet_medical",
    "laboratory": "laboratoire",
    "physiotherapist": "kinesitherapie",
    "optometrist": "opticien",
    "centre": "centre_sante",
}

COLONNES = [
    "nom", "categorie", "specialites", "praticien", "adresse", "quartier", "ville", "boite_postale",
    "telephones", "email", "site_web", "horaires", "ouvert_24h", "sources", "notes",
    "latitude", "longitude", "osm_ref",
]


def categorie(tags: dict) -> str | None:
    if tags.get("shop") == "optician":
        return "opticien"
    if tags.get("healthcare:speciality") in ("radiology", "diagnostic_radiology"):
        return "imagerie"
    for cle in ("amenity", "healthcare"):
        if tags.get(cle) in CATEGORIE_OSM:
            return CATEGORIE_OSM[tags[cle]]
    return "autre" if "healthcare" in tags else None


def nettoyer(valeur: str) -> str:
    return " ".join((valeur or "").replace("\t", " ").split())


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--overpass", default="https://overpass-api.de/api/interpreter")
    args = parser.parse_args()

    corps = urllib.parse.urlencode({"data": REQUETE}).encode()
    requete = urllib.request.Request(args.overpass, data=corps, headers={"User-Agent": "doktis-annuaire/1.0"})
    with urllib.request.urlopen(requete, timeout=200) as reponse:
        elements = json.load(reponse)["elements"]

    lignes = []
    for el in elements:
        tags = el.get("tags", {})
        nom = nettoyer(tags.get("name") or tags.get("name:fr") or "")
        cat = categorie(tags)
        ville = nettoyer(tags.get("addr:city", ""))
        if not nom or not cat or not ville:
            # Sans ville, la fiche ne peut pas être rattachée : à géocoder plus tard.
            continue
        lat = el.get("lat") or el.get("center", {}).get("lat")
        lon = el.get("lon") or el.get("center", {}).get("lon")
        telephones = " / ".join(
            nettoyer(t) for cle in ("phone", "contact:phone", "contact:mobile")
            for t in tags.get(cle, "").split(";") if t.strip()
        )
        lignes.append({
            "nom": nom,
            "categorie": cat,
            "specialites": nettoyer(tags.get("healthcare:speciality", "")).replace(";", "; "),
            "praticien": "",
            "adresse": nettoyer(" ".join(filter(None, [tags.get("addr:housenumber"), tags.get("addr:street")]))),
            "quartier": nettoyer(tags.get("addr:suburb", "")),
            "ville": ville,
            "boite_postale": "",
            "telephones": telephones,
            "email": nettoyer(tags.get("email") or tags.get("contact:email", "")),
            "site_web": nettoyer(tags.get("website") or tags.get("contact:website", "")),
            "horaires": nettoyer(tags.get("opening_hours", "")),
            "ouvert_24h": "oui" if tags.get("opening_hours") == "24/7" else "",
            "sources": "OSM",
            "notes": "",
            "latitude": f"{lat:.7f}" if lat else "",
            "longitude": f"{lon:.7f}" if lon else "",
            "osm_ref": f"{el['type']}/{el['id']}",
        })

    with (ICI / "donnees_osm.tsv").open("w", encoding="utf-8") as f:
        f.write("\t".join(COLONNES) + "\n")
        for ligne in lignes:
            f.write("\t".join(ligne[c] for c in COLONNES) + "\n")

    print(f"{len(elements)} objets OSM reçus, {len(lignes)} fiches exploitables écrites dans donnees_osm.tsv")


if __name__ == "__main__":
    main()
