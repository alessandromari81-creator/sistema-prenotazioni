#!/bin/bash
# Ricostruisce e riavvia SOLO il frontend della demo (container "web") con il codice attuale.
#
# Riavviare Docker non basta: l'immagine "web" è una fotografia del frontend scattata al momento della build,
# e va ricostruita a ogni modifica. Database e dati non vengono toccati.
#
# La versione (APP_VERSION) deve essere uguale a quella dell'API, altrimenti l'app mostra l'avviso "nuova versione".
# Se non la indichi tu, si prende quella con cui sta già girando l'API (o "dev" se non c'è).
#
# Uso:  ./aggiorna-frontend.sh            (stessa versione dell'API)
#       APP_VERSION=abc123 ./aggiorna-frontend.sh

set -e
cd "$(dirname "$0")"

VERSION="${APP_VERSION:-$(docker compose exec -T app printenv APP_VERSION 2>/dev/null | tr -d '\r' || true)}"
VERSION="${VERSION:-dev}"

echo "Ricostruisco il frontend con versione: $VERSION"
APP_VERSION="$VERSION" docker compose up -d --build web

echo "Fatto. Ricarica la pagina nel browser (Cmd+Shift+R) per vedere le modifiche."
