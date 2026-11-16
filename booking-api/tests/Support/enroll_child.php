<?php

/**
 * Processo figlio del test di concorrenza sulle iscrizioni (vedi tests/Feature/Concurrency/EnrollmentConcurrencyTest.php).
 *
 * Non è un test: è uno script che il test lancia in più copie contemporaneamente. Ogni copia è un processo PHP vero,
 * con la sua connessione al database, come lo sarebbero due richieste arrivate insieme al server.
 *
 * Uso: php enroll_child.php <id utente> <id corso>
 *
 * Protocollo con il test, in tre passi:
 *  1. avvia Laravel, scrive "READY" su una riga e aspetta;
 *  2. quando il test scrive "GO" su una riga (a tutti i figli quasi nello stesso istante) chiama EnrollmentService::enroll();
 *  3. scrive su una riga un JSON con l'esito: {"result": "ok"} oppure {"result": "refused", "message": ...}
 *     (regola di business non rispettata) oppure {"result": "error", "message": ...} (qualunque altro errore, per esempio un deadlock).
 */

use App\Models\Course;
use App\Models\User;
use App\Services\EnrollmentService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

[, $userId, $courseId] = $argv;

// Carica i modelli prima del via, così dopo "GO" resta da fare solo l'iscrizione
$user = User::findOrFail((int) $userId);
$course = Course::findOrFail((int) $courseId);
$service = $app->make(EnrollmentService::class);

fwrite(STDOUT, "READY\n");
fflush(STDOUT);
fgets(STDIN); // aspetta "GO"

try {
    $service->enroll($user, $course);
    $outcome = ['result' => 'ok'];
} catch (ValidationException $e) {
    $outcome = ['result' => 'refused', 'message' => collect($e->errors())->flatten()->first()];
} catch (Throwable $e) {
    $outcome = ['result' => 'error', 'message' => $e::class.': '.$e->getMessage()];
}

fwrite(STDOUT, json_encode($outcome)."\n");
fflush(STDOUT);
