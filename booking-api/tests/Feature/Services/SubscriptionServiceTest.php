<?php

namespace Tests\Feature\Services;

use App\Models\Enrollment;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** Gli abbonamenti: assegnazione, sovrapposizioni, annullamento e abbonamento attivo di un utente. */
class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    private SubscriptionService $service;

    private User $user;

    private SubscriptionPlan $plan;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SubscriptionService::class);
        $this->user = User::factory()->create();
        $this->plan = SubscriptionPlan::factory()->create(['duration_days' => 30, 'max_courses' => 2]);
    }

    /** Controlla che $action lanci una ValidationException con quel messaggio su quel campo. */
    private function assertInvalid(callable $action, string $field, string $message): void
    {
        try {
            $action();
        } catch (ValidationException $e) {
            $this->assertSame($message, $e->errors()[$field][0] ?? null);

            return;
        }

        $this->fail('Doveva lanciare una ValidationException.');
    }

    // ---------- assign ----------

    public function test_assign_starts_today_and_the_end_date_is_inclusive(): void
    {
        $subscription = $this->service->assign($this->user, $this->plan);

        $this->assertSame(now()->toDateString(), $subscription->starts_on->toDateString());
        // 30 giorni a partire da oggi: l'ultimo giorno valido è il ventinovesimo dopo oggi
        $this->assertSame(now()->addDays(29)->toDateString(), $subscription->ends_on->toDateString());
        $this->assertSame($this->plan->id, $subscription->subscription_plan_id);
        $this->assertTrue($subscription->relationLoaded('plan'));
    }

    public function test_assign_can_start_in_the_future(): void
    {
        $start = now()->addDays(10);

        $subscription = $this->service->assign($this->user, $this->plan, $start);

        $this->assertSame($start->toDateString(), $subscription->starts_on->toDateString());
        $this->assertSame($start->copy()->addDays(29)->toDateString(), $subscription->ends_on->toDateString());
    }

    public function test_assign_rejects_an_inactive_plan(): void
    {
        $plan = SubscriptionPlan::factory()->inactive()->create();

        $this->assertInvalid(
            fn () => $this->service->assign($this->user, $plan),
            'subscription_plan_id',
            'Questo piano non è più assegnabile.'
        );
        $this->assertDatabaseCount('subscriptions', 0);
    }

    public function test_assign_rejects_a_period_that_overlaps_an_existing_subscription(): void
    {
        $this->service->assign($this->user, $this->plan);   // da oggi a +29

        $this->assertInvalid(
            fn () => $this->service->assign($this->user, $this->plan, now()->addDays(15)),
            'starts_on',
            'L\'utente ha già un abbonamento in questo periodo.'
        );
        $this->assertDatabaseCount('subscriptions', 1);
    }

    public function test_assign_rejects_a_new_subscription_that_ends_inside_an_existing_one(): void
    {
        $this->service->assign($this->user, $this->plan, now()->addDays(10));   // da +10 a +39

        // Parte oggi e dura 30 giorni: finisce a +29, dentro il periodo già assegnato
        $this->expectException(ValidationException::class);
        $this->service->assign($this->user, $this->plan);
    }

    public function test_assign_allows_a_subscription_that_starts_the_day_after_the_previous_ends(): void
    {
        $this->service->assign($this->user, $this->plan);   // finisce a +29

        $second = $this->service->assign($this->user, $this->plan, now()->addDays(30));

        $this->assertSame(now()->addDays(30)->toDateString(), $second->starts_on->toDateString());
        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_a_cancelled_subscription_does_not_block_a_new_one(): void
    {
        Subscription::factory()->cancelled()->create(['user_id' => $this->user->id, 'subscription_plan_id' => $this->plan->id]);

        $this->service->assign($this->user, $this->plan);

        $this->assertDatabaseCount('subscriptions', 2);
    }

    public function test_the_subscription_of_another_user_does_not_block(): void
    {
        $this->service->assign(User::factory()->create(), $this->plan);

        $this->service->assign($this->user, $this->plan);

        $this->assertDatabaseCount('subscriptions', 2);
    }

    // ---------- activeFor ----------

    public function test_active_for_returns_the_current_subscription_with_its_plan(): void
    {
        $created = $this->service->assign($this->user, $this->plan);

        $active = $this->service->activeFor($this->user);

        $this->assertSame($created->id, $active->id);
        $this->assertTrue($active->relationLoaded('plan'));
    }

    public function test_active_for_is_null_without_a_subscription(): void
    {
        $this->assertNull($this->service->activeFor($this->user));
    }

    public function test_active_for_ignores_expired_cancelled_and_future_subscriptions(): void
    {
        Subscription::factory()->expired()->create(['user_id' => $this->user->id]);
        Subscription::factory()->cancelled()->create(['user_id' => $this->user->id]);
        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'starts_on' => now()->addDays(60)->toDateString(),
            'ends_on' => now()->addDays(89)->toDateString(),
        ]);

        $this->assertNull($this->service->activeFor($this->user));
    }

    public function test_the_subscription_is_active_on_its_first_and_last_day(): void
    {
        $subscription = $this->service->assign($this->user, $this->plan);

        $this->assertNotNull($this->service->activeFor($this->user, $subscription->starts_on));
        $this->assertNotNull($this->service->activeFor($this->user, $subscription->ends_on));
        $this->assertNull($this->service->activeFor($this->user, $subscription->ends_on->copy()->addDay()));
        $this->assertNull($this->service->activeFor($this->user, $subscription->starts_on->copy()->subDay()));
    }

    public function test_active_for_ignores_other_users_subscriptions(): void
    {
        $this->service->assign(User::factory()->create(), $this->plan);

        $this->assertNull($this->service->activeFor($this->user));
    }

    // ---------- cancel ----------

    public function test_cancel_marks_the_subscription_as_cancelled(): void
    {
        $subscription = $this->service->assign($this->user, $this->plan);

        $result = $this->service->cancel($subscription);

        $this->assertNotNull($result->cancelled_at);
        $this->assertNull($this->service->activeFor($this->user));
    }

    public function test_cancelling_twice_is_refused(): void
    {
        $subscription = $this->service->assign($this->user, $this->plan);
        $this->service->cancel($subscription);

        $this->assertInvalid(
            fn () => $this->service->cancel($subscription->fresh()),
            'subscription',
            'L\'abbonamento è già annullato.'
        );
    }

    // ---------- usage ----------

    public function test_usage_counts_active_enrollments_and_remaining_courses(): void
    {
        $this->service->assign($this->user, $this->plan);   // max 2 corsi
        Enrollment::factory()->create(['user_id' => $this->user->id]);
        Enrollment::factory()->cancelled()->create(['user_id' => $this->user->id]);   // non conta

        $usage = $this->service->usage($this->user);

        $this->assertSame(1, $usage['courses_used']);
        $this->assertSame(1, $usage['courses_left']);
        $this->assertNotNull($usage['subscription']);
    }

    public function test_usage_without_a_subscription_has_no_courses_left(): void
    {
        $usage = $this->service->usage($this->user);

        $this->assertNull($usage['subscription']);
        $this->assertSame(0, $usage['courses_left']);
    }

    public function test_courses_left_never_goes_below_zero(): void
    {
        // Abbonamento da un solo corso ma due iscrizioni (per esempio dopo un cambio di piano): niente numeri negativi
        $plan = SubscriptionPlan::factory()->create(['max_courses' => 1]);
        $this->service->assign($this->user, $plan);
        Enrollment::factory()->count(2)->create(['user_id' => $this->user->id]);

        $this->assertSame(0, $this->service->usage($this->user)['courses_left']);
    }
}
