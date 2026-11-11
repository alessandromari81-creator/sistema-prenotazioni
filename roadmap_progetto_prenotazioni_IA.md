# Roadmap di sviluppo -- Progetto Prenotazioni

## Documento di riferimento per ChatGPT, Claude e altre IA

**Scopo:** trasformare il progetto in una piccola applicazione
gestionale completa, utile per studiare in modo pratico Laravel e
Angular e per dimostrare competenze full-stack.

Questo documento è pensato per essere letto da qualsiasi assistente IA.
Contiene il contesto noto del progetto, le funzionalità già presenti,
una roadmap di sviluppo, gli obiettivi didattici e le regole da seguire
quando si propone o si implementa una modifica.

------------------------------------------------------------------------

# 1. Contesto del progetto

Il progetto è un'applicazione web per la gestione di risorse
prenotabili.

-   **Backend:** Laravel, API REST, autenticazione e autorizzazioni.
-   **Frontend:** Angular standalone, Angular Material, SCSS.
-   **Date e orari:** Luxon.
-   **Database:** relazionale, gestito tramite Eloquent.
-   **Obiettivo didattico:** esercitarsi con funzionalità reali di
    Laravel e Angular, evitando di limitarsi ai CRUD più semplici.

L'applicazione distingue almeno due tipologie di utente:

-   **Utente:** consulta le risorse e gestisce le proprie prenotazioni.
-   **Amministratore:** gestisce le risorse e può avere accesso alla
    gestione generale.

È importante verificare il codice effettivo prima di dare per scontati
dettagli non descritti in questo documento.

# 2. Funzionalità già realizzate o presenti nel progetto

In base al contesto disponibile, il progetto comprende:

## Backend Laravel

-   API per le risorse prenotabili (`bookables`).
-   Gestione delle disponibilità e delle fasce orarie.
-   API per ottenere gli slot disponibili in una determinata data.
-   Creazione delle prenotazioni.
-   Annullamento delle prenotazioni.
-   Elenco delle prenotazioni.
-   Distinzione tra utente normale e amministratore.
-   Relazioni Eloquent tra prenotazioni, risorse e utenti.
-   Validazione delle richieste.
-   Uso di transazioni database e `lockForUpdate()` nella creazione
    delle prenotazioni, per proteggere la disponibilità da prenotazioni
    concorrenti.
-   Paginazione Laravel tramite `paginate()`.

## Frontend Angular

-   Componenti standalone.
-   Servizio HTTP centralizzato (`BookingService`).
-   Modelli TypeScript e interfaccia generica `PaginatedResponse<T>`.
-   Angular Material Table, Button, Icon, Chips e altri componenti
    Material.
-   Elenco delle risorse prenotabili in formato card.
-   Gestione amministrativa delle risorse.
-   Elenco personale delle prenotazioni.
-   Ricerca, ordinamento e paginazione server-side in fase di
    integrazione.
-   Signals per lo stato dei componenti.
-   RxJS per le chiamate HTTP e la gestione della ricerca.
-   Luxon per il controllo delle date e degli orari.
-   Interceptor HTTP e gestione centralizzata degli errori, già oggetto
    di lavoro.
-   Routing Angular e protezione delle aree dell'applicazione.

**Nota:** prima di implementare una nuova funzionalità, l'IA deve
chiedere o esaminare i file pertinenti e verificare cosa sia già stato
effettivamente completato. La roadmap non è una dichiarazione che ogni
voce sia già implementata o testata.

# 3. Roadmap delle funzionalità

Le attività sono divise per aree. L'ordine è indicativo: si può adattare
in base allo stato reale del codice e alle priorità del progetto.

## A. Tabelle, ricerca e navigazione

1.  Uniformare tutte le tabelle dell'applicazione.
    -   Ricerca con debounce.
    -   Filtri combinabili.
    -   Ordinamento server-side.
    -   Paginazione server-side.
    -   Gestione dello stato di caricamento, errore e risultato vuoto.
    -   Parametri di ricerca conservati nell'URL, dove utile.
2.  Migliorare la gestione delle prenotazioni.
    -   Filtri per stato, risorsa e intervallo di date.
    -   Ordinamento per data, creazione e altri campi utili.
    -   Distinzione tra prenotazione confermata, annullata e conclusa.
    -   Aggiornamento corretto della tabella dopo un annullamento.

## B. Calendario delle prenotazioni

3.  Realizzare una vista calendario.
    -   Vista mensile, settimanale e giornaliera.
    -   Visualizzazione delle prenotazioni.
    -   Selezione di una risorsa.
    -   Visualizzazione degli slot liberi e occupati.
    -   Apertura del dettaglio di una prenotazione.
    -   Navigazione tra date.
4.  Integrare il calendario con Laravel.
    -   Endpoint per recuperare gli eventi in un intervallo temporale.
    -   Filtri per risorsa e stato.
    -   Risposte API efficienti.
    -   Controllo dei permessi sui dati restituiti.
5.  Gestire le modifiche alle prenotazioni.
    -   Spostamento a un altro giorno o orario.
    -   Verifica della disponibilità prima della modifica.
    -   Protezione contro le modifiche concorrenti.
    -   Transazione database per garantire la coerenza.

## C. Gestione utenti, ruoli e autorizzazioni

6.  Realizzare la gestione utenti lato amministratore.
    -   Elenco utenti con ricerca e paginazione.
    -   Dettaglio utente.
    -   Modifica dei dati.
    -   Attivazione e disattivazione account, se prevista.
    -   Visualizzazione dello storico delle prenotazioni.
7.  Approfondire ruoli e permessi.
    -   Ruoli utente, operatore e amministratore.
    -   Laravel Policies e Gates.
    -   Middleware per proteggere gli endpoint.
    -   Controlli di autorizzazione per singola risorsa.
    -   Verifica che il frontend non sia considerato un confine di
        sicurezza: i controlli effettivi devono essere sul backend.
8.  Completare le funzioni dell'account.
    -   Modifica profilo.
    -   Cambio password.
    -   Recupero password.
    -   Eventuale avatar.
    -   Preferenze di notifica.

## D. Notifiche, email e attività pianificate

9.  Implementare le notifiche.
    -   Conferma di una nuova prenotazione.
    -   Notifica di annullamento.
    -   Notifica di modifica.
    -   Notifiche Laravel su database ed email.
    -   Centro notifiche nel frontend.
    -   Segnare una notifica come letta.
10. Implementare promemoria automatici.
    -   Promemoria prima della prenotazione.
    -   Laravel Scheduler.
    -   Job e Queue.
    -   Gestione degli invii falliti.
    -   Evitare l'invio duplicato dello stesso promemoria.
11. Approfondire gli eventi applicativi.
    -   Events e Listeners.
    -   Separazione della logica dal controller.
    -   Observer Eloquent per eventi sui modelli, quando appropriato.

## E. Dashboard, statistiche e report

12. Creare una dashboard amministrativa.
    -   Numero di prenotazioni per periodo.
    -   Prenotazioni confermate e annullate.
    -   Risorse più utilizzate.
    -   Tasso di occupazione.
    -   Andamento mensile.
    -   Filtri temporali.
13. Implementare report ed esportazioni.
    -   Esportazione CSV.
    -   Generazione PDF.
    -   Download dal frontend Angular.
    -   Report filtrati per periodo e risorsa.
    -   Gestione dei report di grandi dimensioni tramite Job, se
        necessario.

## F. File e immagini

14. Aggiungere immagini alle risorse.
    -   Upload da Angular.
    -   Anteprima prima del salvataggio.
    -   Validazione di formato e dimensione.
    -   Laravel Storage.
    -   Sostituzione ed eliminazione delle immagini.
    -   Autorizzazioni e gestione sicura dei file.
15. Eventualmente aggiungere allegati alle prenotazioni.
    -   Upload e download.
    -   Limiti di dimensione.
    -   Tipi MIME consentiti.
    -   Accesso riservato agli utenti autorizzati.

## G. Funzionalità realtime

16. Aggiornare in tempo reale disponibilità e prenotazioni.
    -   Laravel Broadcasting.
    -   Laravel Reverb o altra soluzione compatibile.
    -   Eventi broadcast.
    -   Sottoscrizione dal frontend.
    -   Aggiornamento degli slot senza ricaricare la pagina.
    -   Gestione della disconnessione e riconnessione.

## H. Funzionalità Angular avanzate

17. Approfondire Reactive Forms.
    -   Form complessi.
    -   Form annidati e FormArray.
    -   Validator personalizzati.
    -   Validator asincroni.
    -   Messaggi di errore riutilizzabili.
18. Approfondire Signals e RxJS.
    -   `signal`, `computed` ed `effect` quando appropriato.
    -   `switchMap` per ricerca e richieste sostituibili.
    -   `combineLatest` per filtri combinati.
    -   `forkJoin` per richieste indipendenti.
    -   Gestione delle sottoscrizioni e del ciclo di vita.
    -   Separazione tra stato locale e stato condiviso.
19. Approfondire il routing.
    -   Lazy loading.
    -   Route guards.
    -   Resolvers.
    -   Parametri e query parameters.
    -   Gestione delle pagine non trovate e degli accessi negati.
20. Approfondire Angular CDK e componenti riutilizzabili.
    -   Dialog e Overlay.
    -   Drag & Drop.
    -   Componenti di conferma.
    -   Tabelle e filtri riutilizzabili.
    -   Direttive e pipe personalizzate.

## I. Qualità, sicurezza e test

21. Migliorare la struttura Laravel.
    -   Form Request per la validazione.
    -   API Resources per uniformare le risposte.
    -   Services o Actions quando servono a isolare la logica
        applicativa.
    -   Gestione coerente delle eccezioni.
    -   Rate limiting.
    -   Cache per dati adatti alla memorizzazione temporanea.
22. Scrivere test backend.
    -   Test delle API.
    -   Test di autenticazione e autorizzazione.
    -   Test di validazione.
    -   Test di creazione e annullamento prenotazioni.
    -   Test di concorrenza, per quanto consentito dall'ambiente di
        test.
    -   Test di ricerca, filtri e paginazione.
23. Scrivere test frontend.
    -   Test dei componenti.
    -   Test dei servizi HTTP.
    -   Test dei form e dei validator.
    -   Test dei flussi di caricamento, errore e successo.
    -   Test delle autorizzazioni lato interfaccia.
24. Curare sicurezza e robustezza.
    -   Verificare sempre i permessi lato server.
    -   Validare input e parametri di ordinamento.
    -   Proteggere gli endpoint.
    -   Gestire errori HTTP in modo uniforme.
    -   Evitare esposizione di dati appartenenti ad altri utenti.
    -   Verificare le operazioni distruttive e le condizioni di gara
        sulle prenotazioni.

## J. Pubblicazione e manutenzione

25. Preparare il deployment.
    -   Configurazione separata per sviluppo e produzione.
    -   Variabili d'ambiente.
    -   Database e migrazioni.
    -   Build Angular per produzione.
    -   HTTPS e configurazione CORS.
    -   Gestione dei processi Queue e Scheduler.
26. Approfondire Docker e infrastruttura.
    -   Container per backend e frontend.
    -   Database.
    -   Volumi e persistenza.
    -   Configurazione di rete.
    -   Log e monitoraggio.
    -   Backup e ripristino.
27. Documentare l'API.
    -   OpenAPI/Swagger.
    -   Descrizione degli endpoint.
    -   Parametri, risposte ed errori.
    -   Esempi di richieste.
    -   Eventuale collezione Postman.

# 4. Ordine consigliato per imparare

Un possibile percorso progressivo:

1.  Completare ricerca, filtri, ordinamento e paginazione in tutte le
    tabelle.
2.  Realizzare il calendario.
3.  Implementare modifica e spostamento delle prenotazioni.
4.  Approfondire Policies, ruoli e gestione utenti.
5.  Aggiungere notifiche, eventi, listener e code.
6.  Realizzare dashboard e statistiche.
7.  Aggiungere upload di immagini e documenti.
8.  Implementare aggiornamenti realtime.
9.  Scrivere una suite di test backend e frontend.
10. Preparare documentazione e deployment.

Non è necessario completare ogni voce prima di passare alla successiva.
Ogni attività deve produrre una funzionalità funzionante e verificabile.

# 5. Come deve lavorare l'assistente IA

Quando ChatGPT, Claude o un'altra IA aiuta a sviluppare il progetto,
deve seguire queste regole.

## Prima di modificare il codice

1.  Chiedere i file necessari se non sono già disponibili nella
    conversazione.
2.  Esaminare la struttura e lo stile già adottati.
3.  Verificare modelli, interfacce, servizi, controller, rotte e
    componenti coinvolti.
4.  Non inventare nomi di campi, relazioni, endpoint o metodi.
5.  Verificare la compatibilità con le versioni effettive di Laravel,
    Angular e delle librerie installate.

## Durante l'implementazione

1.  Procedere un file alla volta, quando questo rende il lavoro più
    comprensibile.
2.  Quando si modifica un file, fornire il contenuto completo se
    l'utente lo richiede o se è più sicuro rispetto a istruzioni
    frammentarie.
3.  Spiegare in modo semplice cosa fa il codice e perché.
4.  Non introdurre astrazioni, librerie o complessità senza un motivo
    concreto.
5.  Riutilizzare servizi, modelli, interfacce e componenti già
    esistenti.
6.  Mantenere coerenza tra nomi, convenzioni e stile del progetto.
7.  Non considerare completata una funzionalità solo perché il codice è
    stato scritto: indicare come provarla.

## Per ogni nuova sfida

L'IA dovrebbe preparare:

-   **Obiettivo:** quale funzionalità si vuole ottenere.
-   **Competenze Laravel:** quali parti del framework si studiano.
-   **Competenze Angular:** quali parti del framework si studiano.
-   **Requisiti:** comportamento atteso per utente e amministratore.
-   **Backend:** endpoint, validazioni, autorizzazioni, logica e
    database.
-   **Frontend:** componenti, servizi, form, stato e navigazione.
-   **Casi limite:** errori, dati mancanti, accessi non autorizzati,
    richieste concorrenti.
-   **Test:** verifiche automatiche e manuali.
-   **Criteri di accettazione:** condizioni concrete per dichiarare
    conclusa la funzionalità.

L'IA non deve necessariamente fornire subito tutto il codice. È
preferibile che accompagni lo sviluppo e lasci spazio all'apprendimento.

# 6. Regole importanti del progetto

-   Non riscrivere parti funzionanti solo per uniformarle a una
    preferenza stilistica.
-   Non modificare API o contratti frontend/backend senza aggiornare
    entrambi i lati.
-   Le autorizzazioni devono essere verificate da Laravel, non soltanto
    da Angular.
-   La disponibilità di uno slot deve essere verificata nuovamente dal
    backend al momento della prenotazione.
-   Le operazioni che coinvolgono disponibilità e prenotazioni devono
    considerare transazioni e concorrenza.
-   Ricerca, ordinamento e paginazione devono essere server-side quando
    si lavora con elenchi potenzialmente grandi.
-   I parametri di ordinamento devono essere limitati a una lista di
    campi consentiti.
-   Le date devono essere trattate in modo coerente tra frontend, API e
    database, considerando il fuso orario.
-   Non aggiungere librerie esterne senza spiegare il vantaggio e
    verificare la compatibilità.
-   Non presumere che una funzionalità sia già stata completata solo
    perché compare nella roadmap.

# 7. Stato di avanzamento

Usare questa sezione per tenere traccia del lavoro. Aggiornare le
caselle quando una funzionalità è stata effettivamente implementata e
verificata.

-   [ ] Tabelle uniformi: ricerca, filtri, ordinamento e paginazione
-   [ ] Calendario delle prenotazioni
-   [ ] Modifica/spostamento prenotazioni
-   [ ] Gestione utenti
-   [ ] Ruoli e Policies
-   [ ] Profilo e recupero password
-   [ ] Notifiche email e database
-   [ ] Promemoria con Scheduler e Queue
-   [ ] Dashboard e statistiche
-   [ ] Esportazione CSV/PDF
-   [ ] Upload immagini e documenti
-   [ ] Registro attività
-   [ ] Aggiornamenti realtime
-   [ ] Test backend
-   [ ] Test frontend
-   [ ] Documentazione API
-   [ ] Deployment
-   [ ] Docker e backup

------------------------------------------------------------------------

## Prompt iniziale da usare con qualsiasi IA

> Leggi il documento Roadmap di sviluppo -- Progetto Prenotazioni.
> Voglio usare questo progetto per studiare concretamente Laravel e
> Angular e costruire un'applicazione full-stack completa.
>
> Aiutami a sviluppare una funzionalità alla volta. Prima di proporre
> modifiche, verifica i file e il codice realmente esistenti: non
> inventare strutture o metodi. Spiegami quali concetti sto imparando,
> proponi un'implementazione coerente con il progetto e indicami come
> testarla.
>
> Procediamo in modo pratico e graduale, senza riscrivere inutilmente
> ciò che funziona. Quando una funzionalità è completata e verificata,
> aggiorniamo la sezione Stato di avanzamento del documento.
