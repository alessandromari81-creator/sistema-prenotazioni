<?php

namespace Tests\Support;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;

/**
 * Strumenti per provare l'autenticazione senza un Keycloak vero.
 *
 * I test generano una coppia di chiavi RSA, firmano da soli i token e fanno finta che Keycloak
 * pubblichi la chiave pubblica (Http::fake): così si prova la verifica reale (firma, scadenza, emittente,
 * destinatario) con token costruiti ad arte, anche sbagliati.
 */
trait FakesKeycloak
{
    /** L'emittente usato nei test (un indirizzo finto: nessuna rete viene toccata). */
    private const TEST_ISSUER = 'http://keycloak.test/realms/palestra';

    /** L'indirizzo da cui Laravel "scarica" le chiavi pubbliche nei test. */
    private const TEST_JWKS_URI = 'http://keycloak.test/realms/palestra/protocol/openid-connect/certs';

    /** La coppia di chiavi di Keycloak finto: generata una volta sola per tutti i test (è lenta). */
    private static ?array $keycloakKeys = null;

    /**
     * Prepara l'ambiente: imposta la configurazione di Keycloak e fa "pubblicare" la chiave pubblica.
     * Da chiamare nel setUp() dei test che usano un token.
     */
    protected function setUpKeycloak(): void
    {
        config([
            'keycloak.issuer' => self::TEST_ISSUER,
            'keycloak.jwks_uri' => self::TEST_JWKS_URI,
            'keycloak.audience' => 'palestra-api',
            'keycloak.admin_role' => 'admin',
            'keycloak.leeway' => 0,
        ]);

        $this->publishKeycloakKeys(['test-key' => $this->keycloakKeyPair()]);
    }

    /**
     * Fa in modo che Keycloak "pubblichi" queste chiavi pubbliche (kid => coppia di chiavi).
     * Serve anche a simulare una rotazione: si cambia l'elenco a metà test.
     *
     * @param  array<string, array{private: string, public: array}>  $keysByKid
     */
    protected function publishKeycloakKeys(array $keysByKid): void
    {
        $keys = [];

        foreach ($keysByKid as $kid => $pair) {
            $keys[] = $pair['public'] + ['kid' => $kid];
        }

        // Si riparte da un client HTTP pulito: con Http::fake() richiamato due volte vincerebbe la prima risposta
        Http::swap(new HttpFactory);
        Http::fake([self::TEST_JWKS_URI => Http::response(['keys' => $keys])]);
    }

    /**
     * Crea un JWT firmato come lo farebbe Keycloak. I claim predefiniti descrivono un utente normale con token
     * valido; si possono sovrascrivere uno per uno per costruire token sbagliati.
     *
     * @param  array<string, mixed>  $claims  claim da aggiungere o sostituire
     * @param  array{private: string, public: array}|null  $signWith  coppia di chiavi con cui firmare (di solito quella di test)
     */
    protected function keycloakToken(array $claims = [], string $kid = 'test-key', ?array $signWith = null): string
    {
        $now = time();

        $payload = array_merge([
            'iss' => self::TEST_ISSUER,
            'aud' => 'palestra-api',
            'sub' => '9d3f1c1e-5a56-4c0e-8d52-2f9b6a1c0001',
            'email' => 'utente@example.com',
            'email_verified' => true,
            'name' => 'Utente Di Prova',
            'realm_access' => ['roles' => []],
            'iat' => $now,
            'exp' => $now + 300,
        ], $claims);

        $pair = $signWith ?? $this->keycloakKeyPair();

        return JWT::encode($payload, $pair['private'], 'RS256', $kid);
    }

    /**
     * Una coppia di chiavi RSA nuova e diversa da quella di Keycloak finto: per simulare un token falsificato
     * o una chiave ruotata.
     *
     * @return array{private: string, public: array}
     */
    protected function anotherKeyPair(): array
    {
        return $this->generateKeyPair();
    }

    /**
     * La coppia di chiavi di Keycloak finto.
     *
     * @return array{private: string, public: array}
     */
    protected function keycloakKeyPair(): array
    {
        return self::$keycloakKeys ??= $this->generateKeyPair();
    }

    /**
     * Genera una coppia di chiavi RSA e ne ricava la parte pubblica nel formato JWK (quello pubblicato da Keycloak).
     *
     * @return array{private: string, public: array}
     */
    private function generateKeyPair(): array
    {
        $resource = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($resource, $privatePem);
        $rsa = openssl_pkey_get_details($resource)['rsa'];

        $base64Url = fn (string $binary) => rtrim(strtr(base64_encode($binary), '+/', '-_'), '=');

        return [
            'private' => $privatePem,
            'public' => [
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $base64Url($rsa['n']),
                'e' => $base64Url($rsa['e']),
            ],
        ];
    }
}
