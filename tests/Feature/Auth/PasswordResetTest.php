<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Mail\PasswordVerificationCodeMail;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_password_link_screen_can_be_rendered(): void
    {
        $response = $this->get('/password/reset');

        $response->assertStatus(200);
    }

    public function test_reset_password_link_can_be_requested(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $this->post('/password/email', [
            'email' => $user->email,
        ]);

        Mail::assertSent(PasswordVerificationCodeMail::class);
    }

    public function test_reset_password_form_redirects_to_code_request_screen(): void
    {
        $response = $this->get('/password/reset/example-token');

        $response->assertRedirect(route('password.request', absolute: false));
    }

    public function test_password_can_be_reset_with_valid_code(): void
    {
        Mail::fake();
        Event::fake();

        $user = User::factory()->create();

        $this->post('/password/email', [
            'email' => $user->email,
        ]);

        Mail::assertSent(PasswordVerificationCodeMail::class, function (PasswordVerificationCodeMail $mail) use ($user) {
            $this->post('/password/verify-code', [
                'email' => $user->email,
                'code' => $mail->code,
            ])->assertSessionHasNoErrors();

            $this->post('/password/reset', [
                'email' => $user->email,
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('login', absolute: false));

            return true;
        });

        Event::assertDispatched(PasswordReset::class);
    }
}
