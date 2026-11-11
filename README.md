# Sistema di prenotazioni

Applicazione web per prenotare risorse (sale, campi, postazioni...) in fasce orarie. Gli utenti scelgono
una risorsa, un giorno e uno slot libero; gli amministratori gestiscono risorse e orari di disponibilità.

Progetto da portfolio: API REST con **Laravel** e interfaccia a pagina singola con **Angular** e
**Angular Material**, in un'unica repository.

```
booking-system/
├── booking-api/        Backend Laravel (API, code, test)
└── booking-frontend/   Frontend Angular
```

## Funzionalità

**Utente**
- Registrazione con **conferma via email**, login e logout
- Elenco delle risorse prenotabili, con ricerca, ordinamento e paginazione
- Scelta della data e degli slot liberi del giorno
- Prenotazione con email di conferma
- "Le mie prenotazioni" con filtri e annullamento

**Amministratore**
- Elenco di tutte le risorse, comprese quelle disattivate
- Creazione, modifica ed eliminazione delle risorse
- Gestione delle fasce orarie settimanali di ogni risorsa (senza sovrapposizioni)
- Vista di tutte le prenotazioni

## Registrazione in due passi

1. Il form invia nome, email, password e indirizzo. L'API salva una **registrazione in attesa**
   (tabella `pending_registrations`) e mette in coda un job che invia la mail. L'utente non esiste ancora.
2. Il link nella mail apre `/confirm-registration` nel frontend, che invia email e token all'API.
   Solo allora viene creato l'utente, con l'email già verificata.

Il token è casuale, salvato come hash e valido 24 ore; una nuova richiesta con la stessa email rende
inutilizzabili i link precedenti. Le richieste scadute si cancellano da sole (`model:prune`). Registrazione,
conferma e login hanno limiti di richieste.

## Regole di business

- Gli slot **non sono salvati nel database**: si calcolano dalle fasce di disponibilità e dalla durata dello slot.
- La prenotazione è validata lato server: risorsa attiva, orario dentro una fascia, allineamento alla griglia
  degli slot, nessuna sovrapposizione.
- La creazione avviene in una transazione con `lockForUpdate` sulla risorsa: due richieste simultanee non
  possono prenotare lo stesso slot.
- Un utente annulla fino a 2 ore prima dell'inizio, l'admin sempre. L'annullamento cambia lo stato in
  `cancelled`, non cancella la riga.
- Gli istanti sono salvati in UTC; le fasce sono nell'ora di `Europe/Rome`.

## Stack

| Parte | Tecnologie |
|---|---|
| Backend | Laravel, PHP, Laravel Sanctum (autenticazione SPA con cookie) |
| Database | PostgreSQL |
| Code e scheduler | code su database (job per le email), `model:prune` pianificato |
| Ambiente locale | Docker con Laravel Sail, Mailpit per le email di prova |
| Frontend | Angular (componenti standalone, signals), Angular Material, RxJS |
| Test | PHPUnit (Feature e Unit) |

## Prova rapida con Docker

Serve solo Docker Desktop. Dalla radice del progetto:

```bash
docker compose up --build
```

La prima volta costruisce le immagini (qualche minuto). Poi:

| Cosa | Indirizzo |
|---|---|
| Applicazione | <http://localhost:8080> |
| Email di prova (Mailpit) | <http://localhost:8025> |

- Entra come admin con `admin@example.com` / `password`, oppure registrati: la mail di conferma si legge su Mailpit.
- Parte tutto da solo: il frontend compilato (nginx, che inoltra `/api` e `/sanctum` al backend), Laravel con
  FrankenPHP, il worker delle email, lo scheduler, Postgres e Mailpit. Migrazioni e dati di prova vengono
  eseguiti al primo avvio.
- `docker compose down` ferma tutto e conserva i dati; `docker compose down -v` cancella anche il database.
- Se le porte sono occupate: `WEB_PORT=9090 MAILPIT_PORT=9025 docker compose up --build`.

> È una configurazione **solo per provare l'app**: chiave, password del database e admin sono quelli di prova, le
> immagini includono le dipendenze di sviluppo (il seeder usa Faker) e il frontend è compilato in modalità
> `development`. Per lavorare sul codice si usa l'avvio qui sotto.

## Requisiti per lo sviluppo

- Docker Desktop
- Node.js 22 e npm
- Angular CLI (`npm install -g @angular/cli`)

## Avvio per lo sviluppo

### 1. Backend

```bash
cd booking-api
cp .env.example .env

# Prima volta: installa le dipendenze PHP con un container temporaneo (la versione PHP deve essere
# compatibile con composer.json, ^8.3)
docker run --rm -v "$(pwd)":/var/www/html -w /var/www/html \
  laravelsail/php84-composer:latest composer install --ignore-platform-reqs

./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
```

Nel `.env` controlla `APP_TIMEZONE=Europe/Rome`, `SANCTUM_STATEFUL_DOMAINS=localhost:4200` e
`FRONTEND_URL=http://localhost:4200`. Il backend risponde su `http://localhost`.

**Worker della coda.** Le email partono solo se è acceso, in un terminale a parte:

```bash
./vendor/bin/sail artisan queue:work
```

Le email di prova si leggono su Mailpit: `http://localhost:8025`.

### 2. Frontend

```bash
cd booking-frontend
npm install
ng serve --proxy-config proxy.conf.json
```

L'app è su `http://localhost:4200`. Il proxy inoltra `/api` e `/sanctum` al backend, così il browser vede
un'unica origine e i cookie di Sanctum funzionano. **Senza `--proxy-config` le chiamate all'API non arrivano
al backend.** Il proxy aggiunge anche l'header `Origin`: serve a Safari, che non invia il `Referer` nelle
richieste GET e altrimenti verrebbe trattato da Sanctum come non autenticato.

## Dati di prova

Il seeder crea:
- un amministratore: `admin@example.com` / `password`
- 3 risorse con orari lunedì-venerdì, 9:00-13:00 e 15:00-19:00

> Credenziali valide solo per lo sviluppo locale: non usarle in produzione.

Gli utenti normali si creano dalla pagina di registrazione (con la conferma via Mailpit).

## Test

```bash
cd booking-api
./vendor/bin/sail artisan test
```

I test usano PostgreSQL con un database dedicato `testing` (vedi `phpunit.xml`).

## API

Autenticazione con cookie di sessione (Sanctum). Prima dei `POST` il client richiede il cookie CSRF a
`GET /sanctum/csrf-cookie`. Le rotte hanno il prefisso `/api`.

| Metodo | Percorso | Descrizione | Accesso |
|---|---|---|---|
| POST | `/register` | Registrazione: salva la richiesta e invia la mail | pubblico |
| POST | `/register/confirm` | Conferma con `email` e `token` e crea l'utente | pubblico |
| POST | `/login` | Login | pubblico |
| POST | `/logout` | Logout | autenticato |
| GET | `/user` | Utente corrente | autenticato |
| GET | `/bookables` | Elenco risorse (`?all=1` include le disattivate) | autenticato (`all` solo admin) |
| GET | `/bookables/{id}` | Dettaglio risorsa con fasce | autenticato |
| POST / PUT / DELETE | `/bookables`, `/bookables/{id}` | Crea, modifica, elimina risorsa | admin |
| GET | `/bookables/{id}/slots?date=AAAA-MM-GG` | Slot liberi del giorno | autenticato |
| POST | `/bookables/{id}/availabilities` | Aggiunge una fascia oraria | admin |
| DELETE | `/availabilities/{id}` | Elimina una fascia oraria | admin |
| GET | `/bookings` | Prenotazioni (le proprie; admin: tutte), con filtri e paginazione | autenticato |
| POST | `/bookings` | Nuova prenotazione | autenticato |
| DELETE | `/bookings/{id}` | Annulla una prenotazione | proprietario o admin |
