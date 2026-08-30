<?php

namespace Tests\Feature;

use App\Enums\BillingPeriod;
use App\Enums\SubscriptionStatus;
use App\Enums\TenantMembershipRole;
use App\Mail\BillingReminderMail;
use App\Mail\PaymentOverdueMail;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\BillingReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BillingReminderServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function makeSubscription(int $daysUntilDue, ?string $status = null): array
    {
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);
        $plan = SubscriptionPlan::factory()->create();

        $subscription = $tenant->subscriptions()->create([
            'subscription_plan_id' => $plan->id,
            'billing_period' => BillingPeriod::Monthly,
            'status' => $status ?? SubscriptionStatus::Active,
            'current_period_start' => now()->subDays(23),
            'current_period_end' => now()->addDays($daysUntilDue),
        ]);

        return [$tenant, $owner, $subscription];
    }

    public function test_sends_a_reminder_for_a_subscription_due_within_the_window(): void
    {
        Mail::fake();
        [, $owner, $subscription] = $this->makeSubscription(5);

        $sent = app(BillingReminderService::class)->sendDueReminders();

        $this->assertSame(1, $sent);
        Mail::assertSent(BillingReminderMail::class, fn ($mail) => $mail->hasTo('owner@example.test'));
        $this->assertSame(
            $subscription->fresh()->current_period_end->toDateString(),
            $subscription->fresh()->last_reminder_period_end->toDateString(),
        );
    }

    public function test_does_not_remind_a_subscription_due_far_in_the_future(): void
    {
        Mail::fake();
        $this->makeSubscription(30);

        $sent = app(BillingReminderService::class)->sendDueReminders();

        $this->assertSame(0, $sent);
        Mail::assertNothingSent();
    }

    public function test_does_not_send_a_duplicate_reminder_for_the_same_billing_cycle(): void
    {
        Mail::fake();
        [, , $subscription] = $this->makeSubscription(5);

        app(BillingReminderService::class)->sendDueReminders();
        Mail::fake(); // reset the sent-mail log for a clean second assertion
        $secondRun = app(BillingReminderService::class)->sendDueReminders();

        $this->assertSame(0, $secondRun);
        Mail::assertNothingSent();
    }

    public function test_renewal_makes_the_subscription_eligible_for_a_fresh_reminder(): void
    {
        [, , $subscription] = $this->makeSubscription(5);
        app(BillingReminderService::class)->sendDueReminders();
        $this->assertNotNull($subscription->fresh()->last_reminder_period_end);

        // Renew for another full period, then simulate time passing until
        // the new due date is inside the reminder window again — a
        // genuinely different period_end (by date) than the one already
        // recorded, so it reads as a fresh cycle rather than the same one.
        $subscription->update(['current_period_end' => now()->addDays(30)]);
        $subscription->update(['current_period_end' => now()->addDays(6)]);

        Mail::fake();
        $sent = app(BillingReminderService::class)->sendDueReminders();

        $this->assertSame(1, $sent);
        Mail::assertSent(BillingReminderMail::class);
    }

    public function test_expires_a_subscription_whose_period_has_lapsed_and_emails_the_owner(): void
    {
        Mail::fake();
        [$tenant, , $subscription] = $this->makeSubscription(-3);

        $expired = app(BillingReminderService::class)->expireLapsedSubscriptions();

        $this->assertSame(1, $expired);
        $this->assertSame(SubscriptionStatus::Expired, $subscription->fresh()->status);
        Mail::assertSent(PaymentOverdueMail::class, fn ($mail) => $mail->hasTo('owner@example.test'));
    }

    public function test_does_not_expire_a_subscription_still_within_its_period(): void
    {
        Mail::fake();
        [, , $subscription] = $this->makeSubscription(5);

        $expired = app(BillingReminderService::class)->expireLapsedSubscriptions();

        $this->assertSame(0, $expired);
        $this->assertSame(SubscriptionStatus::Active, $subscription->fresh()->status);
    }
}
