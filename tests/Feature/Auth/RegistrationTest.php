<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_is_not_available(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertNotFound();
        $this->assertGuest();
    }

    public function test_cli_command_can_create_a_user_interactively(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Name', 'CLI User')
            ->expectsQuestion('Email', 'CLI@Example.com')
            ->expectsQuestion('Password', 'password')
            ->expectsQuestion('Confirm password', 'password')
            ->expectsOutput('User cli@example.com created successfully.')
            ->assertExitCode(0);

        $user = User::query()->where('email', 'cli@example.com')->first();

        $this->assertNotNull($user);
        $this->assertSame('CLI User', $user->name);
        $this->assertTrue(Hash::check('password', $user->password));
    }

    public function test_cli_command_rejects_invalid_user_input(): void
    {
        User::factory()->create(['email' => 'existing@example.com']);

        $this->artisan('user:create')
            ->expectsQuestion('Name', '')
            ->expectsQuestion('Email', 'existing@example.com')
            ->expectsQuestion('Password', 'short')
            ->expectsQuestion('Confirm password', 'different')
            ->expectsOutputToContain('The name field is required.')
            ->expectsOutputToContain('The email has already been taken.')
            ->expectsOutputToContain('The password field confirmation does not match.')
            ->assertExitCode(1);
    }
}
