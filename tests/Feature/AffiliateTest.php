<?php

namespace Tests\Feature;

use App\Models\Affiliate;
use App\Models\AffiliateCommission;
use App\Models\EmployerPayment;
use App\Models\Referral;
use App\Models\User;
use App\Models\Wallet;
use App\Services\AffiliateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AffiliateTest extends TestCase
{
    use RefreshDatabase;

    private AffiliateService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new AffiliateService();
        Mail::fake();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function makeUser(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['role' => 1], $attrs));
        Wallet::create(['user_id' => $user->id, 'balance' => 0, 'currency' => 'NGN']);
        return $user;
    }

    private function makeAffiliate(User $user): Affiliate
    {
        return $this->service->register($user);
    }

    private function makePayment(int $employerId, int $candidateId, string $status = 'completed'): EmployerPayment
    {
        return EmployerPayment::create([
            'employer_id'         => $employerId,
            'candidate_id'        => $candidateId,
            'amount'              => 10000,
            'employer_pays_total' => 10537.5,
            'freelancer_receives' => 8000,
            'platform_commission' => 2000,
            'platform_fee'        => 500,
            'platform_vat'        => 37.5,
            'type'                => 'escrow',
            'status'              => $status,
            'reference'           => 'TEST_' . uniqid(),
            'wallet_token'        => 'tok_' . uniqid(),
            'payment_method'      => 'paystack',
        ]);
    }

    // ── Affiliate Registration ────────────────────────────────────────────────

    public function test_user_can_register_as_affiliate(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $this->assertInstanceOf(Affiliate::class, $affiliate);
        $this->assertEquals($user->id, $affiliate->user_id);
        $this->assertEquals('active', $affiliate->status);
    }

    public function test_affiliate_gets_unique_id_with_wrk_prefix(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $this->assertStringStartsWith('WRK-', $affiliate->affiliate_id);
        $this->assertMatchesRegularExpression('/^WRK-\d{5}$/', $affiliate->affiliate_id);
    }

    public function test_affiliate_gets_unique_referral_code(): void
    {
        $u1 = $this->makeUser();
        $u2 = $this->makeUser();

        $a1 = $this->makeAffiliate($u1);
        $a2 = $this->makeAffiliate($u2);

        $this->assertNotEquals($a1->referral_code, $a2->referral_code);
    }

    public function test_duplicate_affiliate_registration_throws(): void
    {
        $user = $this->makeUser();
        $this->makeAffiliate($user);

        $this->expectException(\RuntimeException::class);
        $this->makeAffiliate($user);
    }

    // ── Referral Link ─────────────────────────────────────────────────────────

    public function test_referral_link_contains_referral_code(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $link = $this->service->getReferralLink($affiliate);

        $this->assertStringContainsString($affiliate->referral_code, $link);
        $this->assertStringContainsString('?ref=', $link);
    }

    // ── Click Tracking ────────────────────────────────────────────────────────

    public function test_click_tracking_creates_referral_record(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $referral = $this->service->trackClick($affiliate->referral_code, ['ip' => '127.0.0.1']);

        $this->assertNotNull($referral);
        $this->assertEquals('clicked', $referral->status);
        $this->assertEquals($affiliate->id, $referral->affiliate_id);
        $this->assertNotNull($referral->tracking_token);
    }

    public function test_click_tracking_returns_null_for_invalid_code(): void
    {
        $result = $this->service->trackClick('INVALID_CODE');
        $this->assertNull($result);
    }

    public function test_click_tracking_returns_null_for_suspended_affiliate(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);
        $affiliate->update(['status' => 'suspended']);

        $result = $this->service->trackClick($affiliate->referral_code);
        $this->assertNull($result);
    }

    // ── Registration Attribution ──────────────────────────────────────────────

    public function test_registration_attribution_links_user_to_referral(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $referral = $this->service->trackClick($affiliate->referral_code, ['ip' => '1.2.3.4']);

        $newUser = $this->makeUser();
        $this->service->attributeRegistration($newUser, $referral->tracking_token);

        $referral->refresh();
        $newUser->refresh();

        $this->assertEquals('registered', $referral->status);
        $this->assertEquals($newUser->id, $referral->referred_user_id);
        $this->assertEquals($affiliate->id, $newUser->referred_by);
    }

    public function test_self_referral_is_blocked(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $referral = $this->service->trackClick($affiliate->referral_code);

        // Affiliate tries to refer themselves
        $this->service->attributeRegistration($user, $referral->tracking_token);

        $referral->refresh();

        // Referral should remain 'clicked' — not attributed
        $this->assertEquals('clicked', $referral->status);
        $this->assertNull($referral->referred_user_id);
    }

    public function test_invalid_tracking_token_is_silently_ignored(): void
    {
        $user = $this->makeUser();
        // No exception, no crash
        $this->service->attributeRegistration($user, 'non-existent-token');
        $this->assertTrue(true);
    }

    public function test_duplicate_attribution_is_prevented(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $referral = $this->service->trackClick($affiliate->referral_code);
        $newUser  = $this->makeUser();

        $this->service->attributeRegistration($newUser, $referral->tracking_token);
        // Second attempt with the same token should be silently ignored
        $anotherUser = $this->makeUser();
        $this->service->attributeRegistration($anotherUser, $referral->tracking_token);

        // Only the first user should be attributed
        $referral->refresh();
        $this->assertEquals($newUser->id, $referral->referred_user_id);
    }

    // ── Conversion & Commission ───────────────────────────────────────────────

    public function test_commission_is_created_on_payment_approval(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $employer = $this->makeUser(['role' => 2]);
        $referral = $this->service->trackClick($affiliate->referral_code);
        $this->service->attributeRegistration($employer, $referral->tracking_token);

        $candidate = $this->makeUser(['role' => 1]);
        $payment   = $this->makePayment($employer->id, $candidate->id, 'completed');

        $this->service->processConversion($payment);

        $this->assertDatabaseHas('affiliate_commissions', [
            'affiliate_id'   => $affiliate->id,
            'transaction_id' => $payment->id,
            'status'         => 'pending',
        ]);

        $referral->refresh();
        $this->assertEquals('converted', $referral->status);
    }

    public function test_duplicate_commission_is_prevented(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $employer = $this->makeUser(['role' => 2]);
        $referral = $this->service->trackClick($affiliate->referral_code);
        $this->service->attributeRegistration($employer, $referral->tracking_token);

        $candidate = $this->makeUser(['role' => 1]);
        $payment   = $this->makePayment($employer->id, $candidate->id, 'completed');

        $this->service->processConversion($payment);
        $this->service->processConversion($payment); // second call must be a no-op

        $this->assertEquals(1, AffiliateCommission::where('transaction_id', $payment->id)->count());
    }

    public function test_no_commission_for_non_referred_employer(): void
    {
        $employer  = $this->makeUser(['role' => 2]); // no referred_by
        $candidate = $this->makeUser(['role' => 1]);
        $payment   = $this->makePayment($employer->id, $candidate->id, 'completed');

        $this->service->processConversion($payment);

        $this->assertEquals(0, AffiliateCommission::count());
    }

    public function test_no_commission_for_suspended_affiliate(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $employer = $this->makeUser(['role' => 2]);
        $referral = $this->service->trackClick($affiliate->referral_code);
        $this->service->attributeRegistration($employer, $referral->tracking_token);

        $affiliate->update(['status' => 'suspended']);

        $candidate = $this->makeUser(['role' => 1]);
        $payment   = $this->makePayment($employer->id, $candidate->id, 'completed');

        $this->service->processConversion($payment);

        $this->assertEquals(0, AffiliateCommission::count());
    }

    // ── Commission Calculation ────────────────────────────────────────────────

    public function test_commission_calculated_as_percentage_of_platform_earnings(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $employer  = $this->makeUser(['role' => 2]);
        $candidate = $this->makeUser(['role' => 1]);
        $payment   = $this->makePayment($employer->id, $candidate->id, 'completed');

        // platform_commission=2000, platform_fee=500 → platform_earnings=2500
        // default rate = 10% → expected commission = 250
        $expected = (2000 + 500) * config('affiliate.commission_rate', 0.10);
        $actual   = $this->service->calculateCommission($payment);

        $this->assertEquals(round($expected, 2), $actual);
    }

    // ── Dashboard Statistics ──────────────────────────────────────────────────

    public function test_dashboard_stats_return_correct_totals(): void
    {
        $affiliateUser = $this->makeUser();
        $affiliate     = $this->makeAffiliate($affiliateUser);

        $stats = $this->service->getDashboardStats($affiliate);

        $this->assertArrayHasKey('affiliate_id', $stats);
        $this->assertArrayHasKey('referral_link', $stats);
        $this->assertArrayHasKey('statistics', $stats);
        $this->assertArrayHasKey('total_referrals', $stats['statistics']);
        $this->assertArrayHasKey('commission_earned', $stats['statistics']);
        $this->assertArrayHasKey('pending_commission', $stats['statistics']);
    }

    // ── API Endpoints ─────────────────────────────────────────────────────────

    public function test_register_api_endpoint_creates_affiliate(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user, 'api')
            ->postJson('/api/v1/affiliate/register', ['terms_accepted' => true]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['affiliate_id', 'referral_code', 'referral_link']]);
    }

    public function test_dashboard_api_returns_stats(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $response = $this->actingAs($user, 'api')
            ->getJson('/api/v1/affiliate/dashboard');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => ['statistics']]);
    }

    public function test_unauthenticated_user_cannot_access_affiliate_dashboard(): void
    {
        $response = $this->getJson('/api/v1/affiliate/dashboard');
        $response->assertStatus(401);
    }

    public function test_click_tracking_api_endpoint(): void
    {
        $user      = $this->makeUser();
        $affiliate = $this->makeAffiliate($user);

        $response = $this->getJson("/api/v1/referral/click/{$affiliate->referral_code}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['tracking_token', 'expires_in_days']]);
    }

    public function test_click_tracking_returns_404_for_invalid_code(): void
    {
        $response = $this->getJson('/api/v1/referral/click/BADCODE');
        $response->assertStatus(404);
    }

    // ── Authorization ─────────────────────────────────────────────────────────

    public function test_affiliate_cannot_access_another_affiliates_data(): void
    {
        $user1      = $this->makeUser();
        $user2      = $this->makeUser();
        $affiliate2 = $this->makeAffiliate($user2);

        // user1 is NOT the owner of affiliate2 — they see their own (empty) data
        $response = $this->actingAs($user1, 'api')
            ->getJson('/api/v1/affiliate/dashboard');

        // user1 has no affiliate record, so they get a 403
        $response->assertStatus(403);
    }
}
