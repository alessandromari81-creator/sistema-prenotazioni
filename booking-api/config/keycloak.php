<?php

/**
 * Impostazioni di Keycloak, il server che gestisce identità, login e password.
 * Laravel non vede mai le password: riceve dal frontend un JWT firmato da Keycloak e lo verifica.
 *
 * Gli indirizzi sono due perché il browser e Laravel raggiungono Keycloak con nomi diversi
 * (vedi issuer e jwks_uri qui sotto).
 */
return [

    /*
     * Emittente atteso (claim "iss" del token): l'indirizzo del realm come lo vede il BROWSER,
     * per esempio http://localhost:8180/realms/palestra. Un token con un "iss" diverso viene rifiutato.
     */
    'issuer' => env('KEYCLOAK_ISSUER', 'http://localhost:8180/realms/palestra'),

    /*
     * Dove scaricare le chiavi pubbliche (JWKS) con cui verificare la firma. Può essere un indirizzo
     * interno alla rete Docker (http://keycloak:8080/...), diverso da quello pubblico dell'emittente.
     */
    'jwks_uri' => env('KEYCLOAK_JWKS_URI', 'http://localhost:8180/realms/palestra/protocol/openid-connect/certs'),

    /*
     * Destinatario atteso (claim "aud"): il nome di questa API. Keycloak lo inserisce nel token con un
     * "audience mapper"; un token pensato per un'altra applicazione viene rifiutato.
     */
    'audience' => env('KEYCLOAK_AUDIENCE', 'palestra-api'),

    /*
     * Ruolo del realm che rende un utente amministratore (si trova in "realm_access.roles" del token).
     */
    'admin_role' => env('KEYCLOAK_ADMIN_ROLE', 'admin'),

    /*
     * Per quanti secondi tenere in cache le chiavi pubbliche, per non chiederle a Keycloak a ogni richiesta.
     */
    'jwks_cache_ttl' => (int) env('KEYCLOAK_JWKS_CACHE_TTL', 3600),

    /*
     * Tolleranza in secondi sulla scadenza del token, per orologi dei server leggermente sfasati.
     */
    'leeway' => (int) env('KEYCLOAK_LEEWAY', 30),
];
