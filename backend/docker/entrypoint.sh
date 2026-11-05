#!/bin/sh
# Démarrage de l'API DOKTIS : configuration, migrations, référentiels, puis Apache.
set -e
cd /var/www/html

# Clé de chiffrement Laravel : fournie telle quelle (APP_KEY), ou dérivée du
# secret aléatoire que Render génère dans APP_KEY_SEED (stable entre déploiements).
if [ -z "${APP_KEY:-}" ]; then
    if [ -z "${APP_KEY_SEED:-}" ]; then
        echo "APP_KEY ou APP_KEY_SEED doit être défini." >&2
        exit 1
    fi
    APP_KEY="base64:$(php -r 'echo base64_encode(hash("sha256", getenv("APP_KEY_SEED"), true));')"
    export APP_KEY
fi

# Apache écoute sur le port attribué par Render.
PORT="${PORT:-10000}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# La configuration est figée avec les variables d'environnement du service.
php artisan config:cache
php artisan route:cache
php artisan view:cache

php artisan migrate --force
php artisan db:seed --class=ReferentielSeeder --force

chown -R www-data:www-data storage bootstrap/cache

exec apache2-foreground
