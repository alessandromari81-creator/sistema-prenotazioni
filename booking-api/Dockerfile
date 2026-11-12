# syntax=docker/dockerfile:1
#
# Immagine del backend per la demo con Docker (non è quella di sviluppo: per quella c'è Laravel Sail).
# Due fasi: nella prima si installano le dipendenze PHP, nella seconda si prepara l'immagine finale.

# --- Fase 1: dipendenze PHP. Includono anche quelle di sviluppo perché il seeder dei dati di prova
# usa le factory, che a loro volta usano Faker (dipendenza di sviluppo).
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --optimize --no-scripts --ignore-platform-reqs

# --- Fase 2: PHP con FrankenPHP (server web integrato). Le dipendenze richiedono PHP >= 8.4.
FROM dunglas/frankenphp:1-php8.5
RUN install-php-extensions pdo_pgsql pcntl opcache
WORKDIR /app
COPY --from=vendor /app /app
COPY docker/entrypoint.sh /usr/local/bin/app-entrypoint
RUN chmod +x /usr/local/bin/app-entrypoint \
    && mkdir -p storage/app/private/course-images storage/app/private/course-materials storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R a+rwX storage bootstrap/cache \
    && php artisan package:discover --ansi

# SERVER_NAME=:80 fa servire il sito in HTTP semplice (senza certificato) sulla porta 80
ENV SERVER_NAME=:80
ENTRYPOINT ["app-entrypoint"]
CMD ["frankenphp", "run", "--config", "/etc/frankenphp/Caddyfile", "--adapter", "caddyfile"]
