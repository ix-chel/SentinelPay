<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AccountFactory extends Factory
{
    protected $model = \App\Models\Account::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'name' => $this->faker->words(3, true),
            'balance' => $this->faker->randomFloat(2, 100, 100000),
            'currency' => 'USD',
            'is_active' => true,
        ];
    }

    public function withBalance(string|float $balance): static
    {
        return $this->state(['balance' => $balance]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function usd(): static
    {
        return $this->state(['currency' => 'USD']);
    }
}
