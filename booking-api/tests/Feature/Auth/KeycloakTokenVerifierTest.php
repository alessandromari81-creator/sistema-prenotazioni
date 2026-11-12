<?php

namespace Tests\Feature\Auth;

use App\Auth\InvalidKeycloakToken;
use App\Auth\KeycloakTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakesKeycloak;
use Tests\TestCase;

/**
 * La verifica del JWT: un token è accettato solo con firma giusta, non scaduto, emittente e destinatario corretti.
 */
class KeycloakTokenVerifierTest extends TestCase
{
    use FakesKeycloak;

    private KeycloakTokenVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpKeycloak();
        $this->verifier = app(KeycloakTokenVerifier::class);
    }

    /** Controlla che la verifica rifiuti il token con InvalidKeycloakToken. */
    private function assertRejected(string $token): void
    {
        try {
            $this->verifier->verify($token);
        } catch (InvalidKeycloakToken) {
            $this->addToAssertionCount(1);

            return;
        }

        $this->fail('Il token doveva essere rifiutato.');
    }

    public function test_a_valid_token_returns_its_claims(): void
    {
        $claims = $this->verifier->verify($this->keycloakToken(['email' => 'mario@example.com']));

        $this->assertSame('mario@example.com', $claims['email']);
        $this->assertSame('palestra-api', $claims['aud']);
    }

    public function test_an_expired_token_is_rejected(): void
    {
        $this->assertRejected($this->keycloakToken(['exp' => time() - 60]));
    }

    public function test_a_token_from_another_issuer_is_rejected(): void
    {
        $this->assertRejected($this->keycloakToken(['iss' => 'http://altro-keycloak.test/realms/palestra']));
    }

    public function test_a_token_for_another_audience_is_rejected(): void
    {
        $this->assertRejected($this->keycloakToken(['aud' => 'un-altra-api']));
    }

    public function test_the_audience_may_be_a_list(): void
    {
        $claims = $this->verifier->verify($this->keycloakToken(['aud' => ['account', 'palestra-api']]));

        $this->assertContains('palestra-api', $claims['aud']);
    }

    public function test_a_token_without_audience_is_rejected(): void
    {
        $this->assertRejected($this->keycloakToken(['aud' => null]));
    }

    public function test_a_token_signed_with_another_key_is_rejected(): void
    {
        // Stesso "kid" della chiave pubblicata, ma firmato da un altro: un tentativo di falsificazione
        $forged = $this->keycloakToken([], 'test-key', $this->anotherKeyPair());

        $this->assertRejected($forged);
    }

    public function test_a_token_using_a_symmetric_algorithm_is_rejected(): void
    {
        // Attacco di "confusione dell'algoritmo": firmare con HS256 usando come segreto la chiave pubblica
        $secret = 'una-chiave-qualsiasi-lunga-almeno-trentadue-caratteri';
        $token = JWT::encode(['iss' => 'http://keycloak.test/realms/palestra', 'aud' => 'palestra-api', 'exp' => time() + 300], $secret, 'HS256', 'test-key');

        $this->assertRejected($token);
    }

    public function test_a_malformed_token_is_rejected(): void
    {
        $this->assertRejected('questo-non-e-un-jwt');
    }

    public function test_keys_are_downloaded_once_and_then_cached(): void
    {
        $this->verifier->verify($this->keycloakToken());
        $this->verifier->verify($this->keycloakToken());
        $this->verifier->verify($this->keycloakToken());

        Http::assertSentCount(1);
    }

    public function test_a_rotated_key_is_picked_up_by_refreshing_the_keys(): void
    {
        // Prima verifica: la chiave "vecchia" finisce in cache
        $this->verifier->verify($this->keycloakToken());

        // Keycloak cambia chiave: pubblica solo quella nuova, con un altro kid
        $newPair = $this->anotherKeyPair();
        $this->publishKeycloakKeys(['new-key' => $newPair]);

        $claims = $this->verifier->verify($this->keycloakToken([], 'new-key', $newPair));

        $this->assertSame('utente@example.com', $claims['email']);
    }

    public function test_unknown_keys_do_not_make_laravel_hammer_keycloak(): void
    {
        $this->verifier->verify($this->keycloakToken());   // 1 download

        // Tre token con "kid" inventati: il ricaricamento delle chiavi è permesso una volta al minuto, non tre
        foreach (['x1', 'x2', 'x3'] as $kid) {
            $this->assertRejected($this->keycloakToken([], $kid, $this->anotherKeyPair()));
        }

        Http::assertSentCount(2);   // il primo download + un solo ricaricamento
    }
}
