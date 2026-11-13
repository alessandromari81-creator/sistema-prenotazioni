# syntax=docker/dockerfile:1
#
# Immagine del frontend per la demo: compila Angular e lo serve con nginx.

# --- Fase 1: compilazione
FROM node:22-alpine AS build
WORKDIR /app
ENV NG_CLI_ANALYTICS=false
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# Versione pubblicata: la scrive nel file letto dall'app. Deve essere uguale a APP_VERSION del backend,
# così l'app si accorge (dall'intestazione X-App-Version) quando ne esiste una più nuova.
ARG APP_VERSION=dev
RUN echo "export const APP_VERSION = '${APP_VERSION}';" > src/environments/version.ts
# Configurazione "development": la build "production" supera il limite di 1 MB del bundle iniziale
# (angular.json) e quindi fallisce.
RUN npx ng build --configuration development

# --- Fase 2: nginx con i file compilati
FROM nginx:1.27-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist/booking-frontend/browser /usr/share/nginx/html
