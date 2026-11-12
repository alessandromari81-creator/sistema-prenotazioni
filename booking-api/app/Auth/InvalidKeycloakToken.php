<?php

namespace App\Auth;

use RuntimeException;

/**
 * Il token ricevuto non è accettabile: manca, è scaduto, è firmato male, viene da un altro emittente
 * o è destinato a un'altra applicazione. L'API risponde 401.
 *
 * Il messaggio serve ai log e ai test: non va mostrato all'utente, per non dare indizi a chi prova a falsificare token.
 */
class InvalidKeycloakToken extends RuntimeException
{
}
