<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['income', 'expense']),
            'category' => fake()->randomElement(['Salary', 'Food', 'Transport', 'Freelance', 'Bills']),
            'amount' => fake()->randomFloat(2, 1, 5000),
            'description' => fake()->optional()->sentence(),
            'transaction_date' => fake()->date(),
        ];
    }
}
