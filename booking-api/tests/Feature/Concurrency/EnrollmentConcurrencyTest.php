<?php

namespace Tests\Feature\Concurrency;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Le iscrizioni fatte nello stesso istante da più processi veri.
 *
 * Gli altri test fanno una richiesta alla volta, quindi non possono vedere una corsa tra due richieste. Qui invece si
 * lanciano più processi PHP (tests/Support/enroll_child.php), ognuno con la sua connessione a Postgres, e li si fa
 * partire insieme: si controlla che i blocchi (transazione + lockForUpdate di EnrollmentService) tengano davvero.
 *
 * Differenze dagli altri test del database:
 *  - i dati devono essere COMMITTATI per davvero, altrimenti gli altri processi non li vedono: per questo
 *    beginDatabaseTransaction() è svuotato e le tabelle si ripuliscono a mano (all'inizio e alla fine);
 *  - la pulizia svuota tutto: per sicurezza si rifiuta di partire se il database non è quello dei test.
 */
class EnrollmentConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Non avvolge il test in una transazione (è il comportamento normale di RefreshDatabase): i processi figli
     * leggono il database da fuori e vedrebbero tabelle vuote.
     */
    public function beginDatabaseTransaction(): void
    {
        //
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->emptyTestDatabase();
    }

    protected function tearDown(): void
    {
        $this->emptyTestDatabase();

        parent::tearDown();
    }

    // ---------- i tre casi ----------

    public function test_with_one_seat_left_only_one_of_many_simultaneous_enrollments_succeeds(): void
    {
        $course = Course::factory()->create(['capacity' => 3]);
        $plan = SubscriptionPlan::factory()->create(['max_courses' => 5]);

        // 8 persone diverse, ognuna con il suo abbonamento, per un corso da 3 posti
        $pairs = [];
        foreach (range(1, 8) as $ignored) {
            $user = User::factory()->create();
            Subscription::factory()->create(['user_id' => $user->id, 'subscription_plan_id' => $plan->id]);
            $pairs[] = [$user->id, $course->id];
        }

        $outcomes = $this->enrollAtTheSameTime($pairs);

        $this->assertNoUnexpectedErrors($outcomes);
        $this->assertCount(3, $this->withResult($outcomes, 'ok'), 'Devono riuscire esattamente 3 iscrizioni (i posti).');
        $this->assertCount(5, $this->withResult($outcomes, 'refused'));
        $this->assertSame(['Il corso è al completo.'], $this->refusalMessages($outcomes));
        $this->assertSame(3, $course->activeEnrollments()->count(), 'Nel database ci devono essere 3 iscrizioni attive, non di più.');
    }

    public function test_the_courses_limit_of_the_plan_holds_with_simultaneous_enrollments_of_the_same_user(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => SubscriptionPlan::factory()->create(['max_courses' => 1])->id,
        ]);

        // La stessa persona si iscrive a 4 corsi diversi (tutti con molti posti) nello stesso istante
        $pairs = [];
        foreach (range(1, 4) as $ignored) {
            $pairs[] = [$user->id, Course::factory()->create(['capacity' => 12])->id];
        }

        $outcomes = $this->enrollAtTheSameTime($pairs);

        $this->assertNoUnexpectedErrors($outcomes);
        $this->assertCount(1, $this->withResult($outcomes, 'ok'), 'Il piano permette 1 corso: ne deve riuscire uno solo.');
        $this->assertCount(3, $this->withResult($outcomes, 'refused'));
        $this->assertSame(['Hai raggiunto il limite di corsi del tuo abbonamento (1).'], $this->refusalMessages($outcomes));
        $this->assertSame(1, Enrollment::where('user_id', $user->id)->where('status', Enrollment::ACTIVE)->count());
    }

    public function test_the_same_person_enrolling_several_times_at_once_is_enrolled_only_once(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['capacity' => 12]);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'subscription_plan_id' => SubscriptionPlan::factory()->create(['max_courses' => 5])->id,
        ]);

        // Un doppio clic, moltiplicato per 4: stesso utente, stesso corso, nello stesso istante
        $outcomes = $this->enrollAtTheSameTime(array_fill(0, 4, [$user->id, $course->id]));

        $this->assertNoUnexpectedErrors($outcomes);
        $this->assertCount(1, $this->withResult($outcomes, 'ok'));
        $this->assertSame(['Sei già iscritto a questo corso.'], $this->refusalMessages($outcomes));
        $this->assertSame(1, Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->count(), 'Una sola riga per coppia corso-utente.');
    }

    // ---------- strumenti ----------

    /**
     * Lancia un processo figlio per ogni coppia [id utente, id corso] e li fa partire insieme.
     *
     * Prima si aspetta che tutti abbiano avviato Laravel ("READY"), poi si manda "GO" a tutti uno dopo l'altro:
     * la partenza è questione di microsecondi. Poi si legge l'esito di ciascuno.
     *
     * @param  list<array{0:int,1:int}>  $pairs
     * @return list<array{result:string,message?:string}>
     */
    private function enrollAtTheSameTime(array $pairs): array
    {
        $script = base_path('tests/Support/enroll_child.php');
        $environment = array_merge(getenv(), [
            // I figli devono usare lo stesso database di test di questo processo
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => 'pgsql',
            'DB_DATABASE' => DB::connection()->getDatabaseName(),
            'DB_URL' => '',
            'CACHE_STORE' => 'array',
            'MAIL_MAILER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
        ]);

        $children = [];
        foreach ($pairs as [$userId, $courseId]) {
            $process = proc_open(
                [PHP_BINARY, $script, (string) $userId, (string) $courseId],
                [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                $pipes,
                base_path(),
                $environment
            );
            $this->assertIsResource($process, 'Non riesco ad avviare un processo figlio.');
            stream_set_timeout($pipes[1], 60);
            $children[] = ['process' => $process, 'pipes' => $pipes];
        }

        foreach ($children as $child) {
            $line = fgets($child['pipes'][1]);

            // L'errore del figlio si legge solo se qualcosa è andato storto: leggerlo sempre resterebbe in attesa all'infinito
            if ($line !== "READY\n") {
                $this->fail('Un figlio non è partito: '.stream_get_contents($child['pipes'][2]));
            }
        }

        foreach ($children as $child) {
            fwrite($child['pipes'][0], "GO\n");
            fflush($child['pipes'][0]);
        }

        $outcomes = [];
        foreach ($children as $child) {
            $outcomes[] = json_decode((string) fgets($child['pipes'][1]), true) ?? ['result' => 'error', 'message' => 'risposta non valida'];
            array_map('fclose', $child['pipes']);
            proc_close($child['process']);
        }

        return $outcomes;
    }

    /** Svuota tutte le tabelle tranne `migrations`. Rifiuta di farlo se il database non è quello dei test. */
    private function emptyTestDatabase(): void
    {
        $this->assertSame('testing', DB::connection()->getDatabaseName(), 'La pulizia svuota le tabelle: solo sul database "testing".');

        $tables = collect(DB::select("select tablename from pg_tables where schemaname = 'public' and tablename <> 'migrations'"))
            ->map(fn ($row) => '"'.$row->tablename.'"')
            ->implode(', ');

        DB::statement("truncate table {$tables} restart identity cascade");
    }

    /** Gli esiti con il risultato indicato ("ok", "refused" o "error"). */
    private function withResult(array $outcomes, string $result): array
    {
        return array_values(array_filter($outcomes, fn ($outcome) => $outcome['result'] === $result));
    }

    /** I messaggi di rifiuto, senza ripetizioni. */
    private function refusalMessages(array $outcomes): array
    {
        return array_values(array_unique(array_column($this->withResult($outcomes, 'refused'), 'message')));
    }

    /** Nessun figlio deve essere finito in un errore imprevisto (per esempio un deadlock o una violazione di unique). */
    private function assertNoUnexpectedErrors(array $outcomes): void
    {
        $this->assertSame([], array_column($this->withResult($outcomes, 'error'), 'message'), 'Errori imprevisti nei processi figli.');
    }
}
