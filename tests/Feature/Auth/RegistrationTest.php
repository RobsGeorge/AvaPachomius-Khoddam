<?php

namespace Tests\Feature\Auth;

use App\Mail\SendOTPEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * The real registration flow: RegisterController validates Arabic names + national
 * id + mobile, creates an unverified user, emails a one-time code, and redirects to
 * OTP verification. It does not authenticate the user immediately.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_users_can_register_and_receive_an_otp(): void
    {
        Mail::fake();

        $response = $this->post(route('register.store'), [
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234567',
            'email' => 'newservant@example.co',
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user', [
            'email' => 'newservant@example.co',
            'is_verified' => false,
        ]);

        Mail::assertSent(SendOTPEmail::class);
        $this->assertGuest();
    }

    public function test_registration_accepts_long_email_addresses(): void
    {
        Mail::fake();

        $email = 'priest1.stmark@demo.khedma.test';

        $response = $this->post(route('register.store'), [
            'first_name' => 'محمد',
            'second_name' => 'جرجس',
            'third_name' => 'يوسف',
            'national_id' => '29001011234568',
            'email' => $email,
            'job' => 'Priest',
            'date_of_birth' => '1998-05-15',
            'mobile_number' => '1012345679',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('user', [
            'email' => $email,
        ]);
    }

    public function test_registration_rejects_non_arabic_names(): void
    {
        Mail::fake();

        $this->post(route('register.store'), [
            'first_name' => 'John',
            'second_name' => 'Paul',
            'third_name' => 'George',
            'national_id' => '29001011234567',
            'email' => 'latin@example.co',
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'mobile_number' => '1012345678',
        ])->assertSessionHasErrors('first_name');

        Mail::assertNotSent(SendOTPEmail::class);
    }
}
