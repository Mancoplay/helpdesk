<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_project_uses_a_dedicated_session_cookie_name(): void
    {
        $this->assertSame('helpdesk_session', config('session.cookie'));
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }

    public function test_login_keeps_another_active_session_when_single_login_is_disabled(): void
    {
        config([
            'session.driver' => 'database',
            'session.concurrent_window' => 2,
            'session.enforce_single_login' => false,
        ]);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'existing-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('sessions', [
            'id' => 'existing-active-session',
        ]);
    }

    public function test_login_clears_recoverable_session_from_same_client_when_single_login_is_enabled(): void
    {
        config([
            'session.driver' => 'database',
            'session.concurrent_window' => 2,
            'session.enforce_single_login' => true,
        ]);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'existing-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this
            ->withHeader('User-Agent', 'PHPUnit')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseMissing('sessions', [
            'id' => 'existing-active-session',
        ]);
    }

    public function test_login_is_blocked_when_another_client_has_an_active_session(): void
    {
        config([
            'session.driver' => 'database',
            'session.concurrent_window_seconds' => 30,
            'session.enforce_single_login' => true,
        ]);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'existing-active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Other Browser',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $response = $this
            ->withHeader('User-Agent', 'PHPUnit')
            ->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        $response->assertRedirect('/');

        $this->assertGuest();
        $this->assertDatabaseHas('sessions', [
            'id' => 'existing-active-session',
        ]);
        $response->assertSessionHasErrors('email');
    }

    public function test_login_is_allowed_when_previous_session_is_stale(): void
    {
        config([
            'session.driver' => 'database',
            'session.concurrent_window' => 2,
        ]);

        $user = User::factory()->create();

        DB::table('sessions')->insert([
            'id' => 'existing-stale-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'test',
            'last_activity' => time() - 600,
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertDatabaseMissing('sessions', [
            'id' => 'existing-stale-session',
        ]);
    }

    public function test_login_redirects_disabled_users_back_to_login(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create([
            'activo' => false,
        ]);
        $user->assignRole('Usuario');

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response
            ->assertRedirect(route('login', absolute: false))
            ->assertSessionHas('disabled_account_error');
    }
}
