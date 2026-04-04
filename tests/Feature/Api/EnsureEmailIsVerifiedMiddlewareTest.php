<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class EnsureEmailIsVerifiedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'verified'])->get('/api/v1/test/verified-only', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_unverified_user_gets_standardized_error_payload(): void
    {
        $user = VerifiableUser::query()->create([
            'name' => 'Unverified User',
            'email' => 'unverified@example.com',
            'password' => 'password',
            'email_verified_at' => null,
        ]);

        $token = $user->createToken('middleware-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/test/verified-only')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'EMAIL_NOT_VERIFIED')
            ->assertJsonPath('error.message', 'Your email address is not verified.');
    }

    public function test_verified_user_passes_through_verified_middleware(): void
    {
        $user = VerifiableUser::query()->create([
            'name' => 'Verified User',
            'email' => 'verified@example.com',
            'password' => 'password',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $token = $user->createToken('middleware-test')->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/v1/test/verified-only')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }
}

class VerifiableUser extends User implements MustVerifyEmail
{
    use MustVerifyEmailTrait;

    protected $table = 'users';
}
