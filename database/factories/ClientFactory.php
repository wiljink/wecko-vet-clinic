<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        $gender = fake()->randomElement(['male', 'female']);

        return [
            'given_name' => fake()->firstName($gender),
            'surname' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'residence_phone' => fake()->numerify('(02) 8### ####'),
            'mobile_phone' => fake()->numerify('09## ### ####'),
            'statement_type' => fake()->randomElement(['email', 'email', 'print', 'none']),
            'is_active' => fake()->boolean(92),
            'appointments_by_email' => true,
            'appointments_by_sms' => fake()->boolean(70),
            'reminders_by_email' => true,
            'reminders_by_sms' => fake()->boolean(60),
        ];
    }
}
