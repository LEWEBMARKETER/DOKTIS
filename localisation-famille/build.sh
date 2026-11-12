#!/usr/bin/env bash
# Construit l'APK sans Android Studio ni Gradle.
# Prérequis (Ubuntu/Debian) : sudo apt install openjdk-17-jdk aapt dalvik-exchange zipalign apksigner
set -euo pipefail
cd "$(dirname "$0")"

VERSION_CODE=1
VERSION_NAME=1.0
SDK_DIR=${SDK_DIR:-.sdk}
ANDROID_JAR="$SDK_DIR/android-all-14.jar"   # API 34 (Robolectric, Maven Central)
KEYSTORE=${KEYSTORE:-famille-proche.jks}
KS_PASS=${KS_PASS:-famille-proche}

mkdir -p "$SDK_DIR"
if [ ! -s "$ANDROID_JAR" ] || [ "$(stat -c%s "$ANDROID_JAR")" -lt 1000000 ]; then
  echo "> Téléchargement de la plateforme Android 14…"
  curl -fSL -o "$ANDROID_JAR" \
    https://repo.maven.apache.org/maven2/org/robolectric/android-all/14-robolectric-10818077/android-all-14-robolectric-10818077.jar
fi

rm -rf build && mkdir -p build/gen build/classes

echo "> Ressources (aapt)"
aapt package -f -m \
  -M AndroidManifest.xml -S res -A assets -I "$ANDROID_JAR" \
  -J build/gen -F build/unsigned.apk \
  --min-sdk-version 26 --target-sdk-version 34 \
  --version-code "$VERSION_CODE" --version-name "$VERSION_NAME"

echo "> Compilation Java"
javac --release 8 -nowarn -encoding UTF-8 -Xlint:-options \
  -classpath "$ANDROID_JAR" -d build/classes \
  $(find build/gen src -name '*.java')

echo "> Dex"
dalvik-exchange --dex --min-sdk-version=26 --output=build/classes.dex build/classes

echo "> Assemblage"
(cd build && zip -q -j unsigned.apk classes.dex)
zipalign -f -p 4 build/unsigned.apk build/aligned.apk

if [ ! -f "$KEYSTORE" ]; then
  echo "> Création de la clé de signature $KEYSTORE (gardez-la pour les mises à jour)"
  keytool -genkeypair -keystore "$KEYSTORE" -storepass "$KS_PASS" -keypass "$KS_PASS" \
    -alias famille -keyalg RSA -keysize 2048 -validity 10000 \
    -dname "CN=Famille Proche" >/dev/null 2>&1
fi

echo "> Signature"
apksigner sign --ks "$KEYSTORE" --ks-pass "pass:$KS_PASS" --ks-key-alias famille \
  --min-sdk-version 26 --out FamilleProche.apk build/aligned.apk
apksigner verify --min-sdk-version 26 FamilleProche.apk
echo "✅ APK prêt : $(pwd)/FamilleProche.apk"
