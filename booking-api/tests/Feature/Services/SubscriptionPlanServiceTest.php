<?php

namespace Tests\Feature\Services;

use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Services\SubscriptionPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/** I piani di abbonamento: un piano già assegnato non si elimina. */
class SubscriptionPlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_unused_plan_can_be_deleted(): void
    {
        $plan = SubscriptionPlan::factory()->create();

        app(SubscriptionPlanService::class)->delete($plan);

        $this->assertDatabaseMissing('subscription_plans', ['id' => $plan->id]);
    }

    public function test_a_plan_assigned_to_users_cannot_be_deleted(): void
    {
        $plan = SubscriptionPlan::factory()->create();
        Subscription::factory()->create(['subscription_plan_id' => $plan->id]);

        try {
            app(SubscriptionPlanService::class)->delete($plan);
            $this->fail('Doveva lanciare una ValidationException.');
        } catch (ValidationException $e) {
            $this->assertSame(
                'Il piano è stato assegnato a degli utenti: disattivalo invece di eliminarlo.',
                $e->errors()['subscription_plan'][0]
            );
        }

        $this->assertDatabaseHas('subscription_plans', ['id' => $plan->id]);
    }
}
