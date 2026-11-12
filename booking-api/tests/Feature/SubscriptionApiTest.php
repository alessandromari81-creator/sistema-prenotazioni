<?php

namespace Tests\Feature;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Piani e abbonamenti vista dall'API: consultazione, gestione (solo admin) e assegnazione agli utenti. */
class SubscriptionApiTest extends TestCase
{
    use RefreshDatabase;

    /** Un utente normale, già autenticato per le richieste del test. */
    private function loginAsUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    /** Un amministratore, già autenticato per le richieste del test. */
    private function loginAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    // ---------- piani ----------

    public function test_a_guest_cannot_see_plans(): void
    {
        $this->getJson('/api/subscription-plans')->assertUnauthorized();
    }

    public function test_users_see_only_active_plans_from_the_shortest(): void
    {
        $this->loginAsUser();
        SubscriptionPlan::factory()->create(['name' => 'Annuale', 'duration_days' => 365]);
        SubscriptionPlan::factory()->create(['name' => 'Mensile', 'duration_days' => 30]);
        SubscriptionPlan::factory()->inactive()->create(['name' => 'Vecchio']);

        $this->getJson('/api/subscription-plans?all=1')   // "all" non vale per gli utenti normali
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.name', 'Mensile')
            ->assertJsonPath('1.name', 'Annuale');
    }

    public function test_an_admin_can_list_inactive_plans_too(): void
    {
        $this->loginAsAdmin();
        SubscriptionPlan::factory()->create();
        SubscriptionPlan::factory()->inactive()->create();

        $this->getJson('/api/subscription-plans')->assertJsonCount(1);
        $this->getJson('/api/subscription-plans?all=1')->assertJsonCount(2);
    }

    public function test_an_admin_can_create_a_plan(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/api/subscription-plans', ['name' => 'Mensile', 'description' => 'Un mese', 'duration_days' => 30, 'max_courses' => 2, 'is_active' => true])
            ->assertCreated()
            ->assertJsonPath('name', 'Mensile')
            ->assertJsonPath('duration_days', 30)
            ->assertJsonPath('max_courses', 2)
            ->assertJsonPath('is_active', true);
    }

    public function test_plan_creation_validates_the_data(): void
    {
        $this->loginAsAdmin();

        $this->postJson('/api/subscription-plans', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'duration_days', 'max_courses']);
        $this->postJson('/api/subscription-plans', ['name' => 'X', 'duration_days' => 0, 'max_courses' => 100])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['duration_days', 'max_courses']);
    }

    public function test_a_normal_user_cannot_manage_plans(): void
    {
        $this->loginAsUser();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson('/api/subscription-plans', ['name' => 'X', 'duration_days' => 30, 'max_courses' => 2])->assertForbidden();
        $this->putJson("/api/subscription-plans/{$plan->id}", ['name' => 'Y'])->assertForbidden();
        $this->deleteJson("/api/subscription-plans/{$plan->id}")->assertForbidden();
    }

    public function test_an_admin_can_update_and_deactivate_a_plan(): void
    {
        $this->loginAsAdmin();
        $plan = SubscriptionPlan::factory()->create(['name' => 'Mensile']);

        $this->putJson("/api/subscription-plans/{$plan->id}", ['name' => 'Mensile plus', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('name', 'Mensile plus')
            ->assertJsonPath('is_active', false);
    }

    public function test_an_unused_plan_can_be_deleted_but_a_used_one_cannot(): void
    {
        $this->loginAsAdmin();
        $unused = SubscriptionPlan::factory()->create();
        $used = SubscriptionPlan::factory()->create();
        Subscription::factory()->create(['subscription_plan_id' => $used->id]);

        $this->deleteJson("/api/subscription-plans/{$unused->id}")->assertNoContent();
        $this->deleteJson("/api/subscription-plans/{$used->id}")->assertUnprocessable()->assertJsonValidationErrors(['subscription_plan']);
    }

    // ---------- assegnazione degli abbonamenti ----------

    public function test_an_admin_can_assign_a_plan_to_a_user(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create(['duration_days' => 30]);

        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => $plan->id])
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('starts_on', now()->toDateString())
            ->assertJsonPath('ends_on', now()->addDays(29)->toDateString())
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('plan.id', $plan->id);
    }

    public function test_the_start_date_can_be_in_the_future_but_not_in_the_past(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();
        $future = now()->addDays(7)->toDateString();

        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => $plan->id, 'starts_on' => $future])
            ->assertCreated()
            ->assertJsonPath('starts_on', $future)
            ->assertJsonPath('is_active', false);

        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => $plan->id, 'starts_on' => now()->subDay()->toDateString()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['starts_on']);
    }

    public function test_overlapping_subscriptions_are_refused(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => $plan->id])->assertCreated();
        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => $plan->id])
            ->assertUnprocessable()
            ->assertJsonPath('errors.starts_on.0', 'L\'utente ha già un abbonamento in questo periodo.');
    }

    public function test_an_inactive_or_unknown_plan_cannot_be_assigned(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        $inactive = SubscriptionPlan::factory()->inactive()->create();

        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => $inactive->id])
            ->assertUnprocessable()->assertJsonValidationErrors(['subscription_plan_id']);
        $this->postJson("/api/users/{$user->id}/subscriptions", ['subscription_plan_id' => 999999])
            ->assertUnprocessable()->assertJsonValidationErrors(['subscription_plan_id']);
        $this->postJson("/api/users/{$user->id}/subscriptions", [])
            ->assertUnprocessable()->assertJsonValidationErrors(['subscription_plan_id']);
    }

    public function test_a_normal_user_cannot_assign_or_list_subscriptions_of_others(): void
    {
        $this->loginAsUser();
        $other = User::factory()->create();
        $plan = SubscriptionPlan::factory()->create();

        $this->postJson("/api/users/{$other->id}/subscriptions", ['subscription_plan_id' => $plan->id])->assertForbidden();
        $this->getJson("/api/users/{$other->id}/subscriptions")->assertForbidden();
    }

    public function test_an_admin_lists_the_subscriptions_of_a_user_from_the_newest(): void
    {
        $this->loginAsAdmin();
        $user = User::factory()->create();
        Subscription::factory()->expired()->create(['user_id' => $user->id]);
        Subscription::factory()->create(['user_id' => $user->id]);
        Subscription::factory()->create();   // di un altro utente

        $this->getJson("/api/users/{$user->id}/subscriptions")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.is_active', true)
            ->assertJsonPath('1.is_active', false);
    }

    // ---------- annullamento ----------

    public function test_an_admin_can_cancel_a_subscription(): void
    {
        $this->loginAsAdmin();
        $subscription = Subscription::factory()->create();

        $this->deleteJson("/api/subscriptions/{$subscription->id}")
            ->assertOk()
            ->assertJsonPath('is_active', false)
            ->assertJsonStructure(['cancelled_at']);
        $this->assertNotNull($subscription->fresh()->cancelled_at);
    }

    public function test_cancelling_twice_is_refused(): void
    {
        $this->loginAsAdmin();
        $subscription = Subscription::factory()->cancelled()->create();

        $this->deleteJson("/api/subscriptions/{$subscription->id}")->assertUnprocessable()->assertJsonValidationErrors(['subscription']);
    }

    public function test_a_normal_user_cannot_cancel_a_subscription(): void
    {
        $this->loginAsUser();
        $subscription = Subscription::factory()->create();

        $this->deleteJson("/api/subscriptions/{$subscription->id}")->assertForbidden();
        $this->assertNull($subscription->fresh()->cancelled_at);
    }
}
