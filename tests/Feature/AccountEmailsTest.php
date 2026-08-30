<?php

namespace Tests\Feature;

use App\Enums\TenantMembershipRole;
use App\Enums\TenantStatus;
use App\Mail\AccountReactivatedMail;
use App\Mail\AccountSuspendedMail;
use App\Mail\PasswordResetMail;
use App\Mail\WelcomeMail;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantOnboardingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class AccountEmailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_registering_sends_a_welcome_email(): void
    {
        Mail::fake();
        SubscriptionPlan::factory()->create(['is_active' => true]);

        app(TenantOnboardingService::class)->register(
            "Aling Nena's Turo-Turo",
            'Nena Reyes',
            'nena@example.test',
            'password123',
        );

        Mail::assertSent(WelcomeMail::class, fn ($mail) => $mail->hasTo('nena@example.test'));
    }

    public function test_registration_succeeds_even_if_the_welcome_email_fails_to_send(): void
    {
        config()->set('mail.default', 'smtp');
        config()->set('mail.mailers.smtp.host', '127.0.0.1');
        config()->set('mail.mailers.smtp.port', 1);

        SubscriptionPlan::factory()->create(['is_active' => true]);

        $tenant = app(TenantOnboardingService::class)->register(
            "Aling Nena's Turo-Turo",
            'Nena Reyes',
            'nena@example.test',
            'password123',
        );

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('users', ['email' => 'nena@example.test']);
    }

    public function test_forgot_password_sends_our_branded_reset_email(): void
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'owner@example.test']);

        Livewire::test('auth.forgot-password')
            ->set('email', 'owner@example.test')
            ->call('sendResetLink')
            ->assertHasNoErrors();

        Mail::assertSent(PasswordResetMail::class, function ($mail) {
            return $mail->hasTo('owner@example.test') && str_contains($mail->resetUrl, '/reset-password/');
        });
    }

    public function test_suspending_a_business_emails_the_owner(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Active]);
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])
            ->call('suspendBusiness');

        Mail::assertSent(AccountSuspendedMail::class, fn ($mail) => $mail->hasTo('owner@example.test'));
    }

    public function test_reactivating_a_business_emails_the_owner(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $tenant = Tenant::factory()->create(['status' => TenantStatus::Suspended]);
        $owner = User::factory()->create(['email' => 'owner@example.test']);
        $tenant->memberships()->create(['user_id' => $owner->id, 'role' => TenantMembershipRole::Owner]);

        $this->actingAs($admin);
        Livewire::test('pages::admin.businesses.show', ['tenant' => $tenant->uuid])
            ->call('reactivateBusiness');

        Mail::assertSent(AccountReactivatedMail::class, fn ($mail) => $mail->hasTo('owner@example.test'));
    }
}
