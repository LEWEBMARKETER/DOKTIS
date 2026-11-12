# Famille Proche 📍

Application Android (APK) pour voir sur une carte la position de quelques proches,
pour leur sécurité. Pas de compte, pas de serveur à payer.

## Fonctions
- **Carte** de tous les membres, avec l'heure de la dernière position, la précision et la batterie.
- **Localiser** : demande à tous les téléphones d'envoyer leur position immédiatement.
- **SOS** : envoie votre position avec une alerte à toute la famille.
- **Itinéraire** vers un proche (ouvre Google Maps ou une autre appli GPS).
- Envoi automatique toutes les 10, 15, 30 ou 60 minutes, et reprise après un redémarrage du téléphone.

## Installation (sur chaque téléphone)
1. Copier `FamilleProche.apk` sur le téléphone et l'ouvrir. Autoriser « Installer des applis inconnues » si Android le demande.
2. Premier téléphone : **Réglages** › prénom › **Créer un code** › **Envoyer aux proches** › **Enregistrer**.
3. Chaque proche : installe l'APK, entre son prénom, colle **le même code**, **Enregistrer**.
4. Sur l'écran principal, appuyer sur **Partager** et accepter la localisation (choisir « Toujours autoriser »
   et « Ne pas restreindre la batterie » pour un suivi fiable).

## Confidentialité et fonctionnement
- Chaque personne active elle-même le partage. Une notification permanente l'indique sur son téléphone,
  avec un bouton « Arrêter le partage ».
- Les positions sont **chiffrées (AES-256-GCM)** avec le code famille avant d'être transmises par le relais public
  gratuit [ntfy.sh](https://ntfy.sh). Le relais ne peut pas les lire et les efface après 12 heures.
  Sans le code, il est impossible de les lire.
- La carte utilise OpenStreetMap (CARTO). Aucune dépendance aux services Google.

## Recompiler
Sous Ubuntu/Debian : `sudo apt install openjdk-17-jdk aapt dalvik-exchange zipalign apksigner`, puis `./build.sh`.
Gardez le fichier `famille-proche.jks` qui est créé : il sert à signer les mises à jour.
