<?php

namespace Gmarineau\AdminPhp\Database\Factories;

use Gmarineau\AdminPhp\Enums\UserRole;
use Gmarineau\AdminPhp\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Admin,
        ];
    }

    public function editor(): static
    {
        return $this->state(['role' => UserRole::Editor]);
    }

    /**
     * Invited, but has not chosen a password yet.
     */
    public function invited(): static
    {
        return $this->state(['password' => null]);
    }

    public function deactivated(): static
    {
        return $this->state(['deactivated_at' => now()]);
    }
}
