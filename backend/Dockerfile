# Image de production pour l'API Laravel (DOKTIS Directory).
# PHP-FPM + Nginx dans un seul conteneur, pilotés par supervisord — le
# serveur de développement `php artisan serve` est volontairement évité
# ici (mono-thread, déconseillé par Laravel lui-même en production).
FROM php:8.3-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        unzip \
        git \
        nginx \
        gettext-base \
        supervisor \
    && docker-php-ext-install pdo pdo_pgsql pgsql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf.template /etc/nginx/templates/default.conf.template
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Render fournit $PORT dynamiquement ; start.sh génère la config Nginx à
# partir du template avant de lancer supervisord (php-fpm + nginx).
EXPOSE 8080
CMD ["/usr/local/bin/start.sh"]
