<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 *
 * Matches the real `user` table (first/second/third name, national_id,
 * mobile_number, is_verified, application_status, ...). The previous default
 * Breeze factory generated a `name`/`email_verified_at` schema that does not
 * exist here, which broke every test using User::factory().
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'second_name' => fake()->firstName(),
            'third_name' => fake()->lastName(),
            'profile_photo' => '',
            'national_id' => (string) fake()->unique()->numerify('##############'), // 14 digits
            'mobile_number' => (string) fake()->unique()->numerify('01#########'),  // 11 digits
            'email' => fake()->unique()->safeEmail(),
            'job' => 'Servant',
            'date_of_birth' => '2000-01-01',
            'password' => static::$password ??= Hash::make('password'),
            'is_verified' => true,
            'is_superadmin' => false,
            'registration_completed' => true,
            'application_status' => User::APPLICATION_STATUS_APPROVED,
            'remember_token' => Str::random(10),
        ];
    }

    /** A user who has not completed OTP verification / registration. */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'registration_completed' => false,
        ]);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_superadmin' => true,
        ]);
    }

    /** A user whose application is still awaiting review. */
    public function pendingApplication(): static
    {
        return $this->state(fn (array $attributes) => [
            'application_status' => User::APPLICATION_STATUS_PENDING_REVIEW,
        ]);
    }
}
