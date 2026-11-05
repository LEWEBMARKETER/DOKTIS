# API DOKTIS (Laravel) pour Render : PHP 8.4 + Apache, écoute sur $PORT.

# --- Dépendances Composer (sans outils de développement) ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# Les extensions PHP sont vérifiées dans l'image finale (PHP 8.4).
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts

# --- Image d'exécution ---
FROM php:8.4-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql intl opcache \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php-production.ini "$PHP_INI_DIR/conf.d/zz-doktis.ini"
COPY docker/apache-laravel.conf /etc/apache2/conf-available/laravel.conf

# Racine web sur public/ et .htaccess de Laravel autorisé.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's/^ServerTokens .*/ServerTokens Prod/' -e 's/^ServerSignature .*/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
    && a2enmod rewrite headers \
    && a2enconf laravel

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && php artisan package:discover --ansi \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x docker/entrypoint.sh

# Render fournit PORT (10000 par défaut).
ENV PORT=10000
EXPOSE 10000

ENTRYPOINT ["docker/entrypoint.sh"]
