#!/bin/sh
set -e

# Render fournit $PORT au démarrage (sa valeur n'est connue qu'à l'exécution,
# jamais au moment du build de l'image) : on l'injecte dans la config Nginx
# juste avant de démarrer.
export PORT="${PORT:-8080}"
envsubst '${PORT}' < /etc/nginx/templates/default.conf.template > /etc/nginx/conf.d/default.conf

exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
