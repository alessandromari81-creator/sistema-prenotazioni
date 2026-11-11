#!/bin/sh
# Eseguito all'avvio di ogni contenitore del backend.
# Solo il contenitore "app" prepara il database; worker e scheduler partono quando "app" è già pronto.
set -e

if [ "${CONTAINER_ROLE:-app}" = "app" ]; then
    php artisan migrate --force

    # Dati di prova (admin e risorse) solo alla prima accensione: il seeder non è ripetibile
    USERS=$(php artisan tinker --execute='echo App\Models\User::count();' | tail -n 1 | tr -d '[:space:]')
    if [ "$USERS" = "0" ]; then
        php artisan db:seed --force
    fi
fi

exec "$@"
