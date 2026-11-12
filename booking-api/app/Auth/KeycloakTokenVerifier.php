<?php

namespace App\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Verifica un JWT rilasciato da Keycloak e ne restituisce le informazioni (i "claim").
 *
 * Un token è accettato solo se:
 *  - la firma è valida per una delle chiavi pubbliche di Keycloak (algoritmo RS256);
 *  - non è scaduto (claim "exp");
 *  - l'emittente (claim "iss") è quello configurato;
 *  - il destinatario (claim "aud") comprende questa API.
 *
 * Le chiavi pubbliche si scaricano una volta e si tengono in cache: non si chiede nulla a Keycloak a ogni richiesta.
 */
class KeycloakTokenVerifier
{
    /** Nome della voce di cache che contiene le chiavi pubbliche. */
    private const CACHE_KEY = 'keycloak.jwks';

    /** Impedisce di riscaricare le chiavi più di una volta al minuto (vedi refreshKeys). */
    private const REFRESH_LOCK_KEY = 'keycloak.jwks.refresh-lock';

    /**
     * Controlla il token e restituisce i suoi claim.
     *
     * @return array<string, mixed> i claim del token (sub, email, realm_access, ...)
     *
     * @throws InvalidKeycloakToken se il token non è valido per qualunque motivo
     */
    public function verify(string $jwt): array
    {
        JWT::$leeway = config('keycloak.leeway');

        $claims = $this->decode($jwt);

        $this->assertIssuer($claims);
        $this->assertAudience($claims);

        return $claims;
    }

    /**
     * Decodifica il token verificandone firma e scadenza. Se la firma non si riesce a controllare con le
     * chiavi in cache, può darsi che Keycloak abbia cambiato chiave (rotazione): si riscaricano una volta e si riprova.
     *
     * @return array<string, mixed>
     */
    private function decode(string $jwt): array
    {
        try {
            return $this->decodeWith($jwt, $this->keys());
        } catch (Throwable $first) {
            // Riprova con chiavi fresche solo se è stato possibile riscaricarle (limite di una al minuto)
            $fresh = $this->refreshKeys();

            if ($fresh === null) {
                throw new InvalidKeycloakToken('Token non valido: '.$first->getMessage(), 0, $first);
            }

            try {
                return $this->decodeWith($jwt, $fresh);
            } catch (Throwable $second) {
                throw new InvalidKeycloakToken('Token non valido: '.$second->getMessage(), 0, $second);
            }
        }
    }

    /**
     * Decodifica il token con l'elenco di chiavi dato. Le chiavi sono vincolate all'algoritmo RS256:
     * un token che dichiara un altro algoritmo (per esempio "none" o HS256) viene rifiutato.
     *
     * @param  array<string, Key>  $keys
     * @return array<string, mixed>
     */
    private function decodeWith(string $jwt, array $keys): array
    {
        $decoded = JWT::decode($jwt, $keys);

        // Da oggetto a array associativo, più comodo da leggere
        return json_decode(json_encode($decoded), true);
    }

    /**
     * Le chiavi pubbliche di Keycloak, prese dalla cache o scaricate se mancano.
     *
     * @return array<string, Key> chiavi indicizzate per "kid" (l'identificativo scritto nell'intestazione del token)
     */
    private function keys(): array
    {
        $jwks = Cache::remember(
            self::CACHE_KEY,
            config('keycloak.jwks_cache_ttl'),
            fn () => $this->downloadKeySet()
        );

        return JWK::parseKeySet($jwks, 'RS256');
    }

    /**
     * Riscarica le chiavi ignorando la cache, ma al massimo una volta al minuto: senza questo limite chi invia token
     * con "kid" inventati costringerebbe Laravel a interrogare Keycloak a ogni richiesta.
     *
     * @return array<string, Key>|null le chiavi nuove, oppure null se il limite non lo consente o Keycloak non risponde
     */
    private function refreshKeys(): ?array
    {
        if (! Cache::add(self::REFRESH_LOCK_KEY, true, 60)) {
            return null;
        }

        try {
            $jwks = $this->downloadKeySet();
        } catch (Throwable) {
            return null;
        }

        Cache::put(self::CACHE_KEY, $jwks, config('keycloak.jwks_cache_ttl'));

        return JWK::parseKeySet($jwks, 'RS256');
    }

    /**
     * Scarica da Keycloak l'elenco delle chiavi pubbliche (formato JWKS).
     *
     * @return array<string, mixed>
     */
    private function downloadKeySet(): array
    {
        return Http::timeout(5)
            ->acceptJson()
            ->get(config('keycloak.jwks_uri'))
            ->throw()
            ->json();
    }

    /**
     * Controlla che il token sia stato emesso dal nostro Keycloak (claim "iss").
     *
     * @param  array<string, mixed>  $claims
     */
    private function assertIssuer(array $claims): void
    {
        if (($claims['iss'] ?? null) !== config('keycloak.issuer')) {
            throw new InvalidKeycloakToken('Emittente non riconosciuto.');
        }
    }

    /**
     * Controlla che il token sia destinato a questa API (claim "aud", che può essere un testo o un elenco).
     *
     * @param  array<string, mixed>  $claims
     */
    private function assertAudience(array $claims): void
    {
        $audience = (array) ($claims['aud'] ?? []);

        if (! in_array(config('keycloak.audience'), $audience, true)) {
            throw new InvalidKeycloakToken('Destinatario non riconosciuto.');
        }
    }
}
