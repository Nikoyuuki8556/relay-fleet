<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'discord_id' => (string) fake()->unique()->numerify('##################'),
            'discord_username' => fake()->userName(),
            'discord_avatar_url' => null,
            'vatsim_cid' => null,
            'vatsim_rating' => null,
            'vatsim_full_name' => null,
            'email' => fake()->unique()->safeEmail(),
        ];
    }
}
