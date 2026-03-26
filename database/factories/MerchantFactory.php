<?php

namespace Database\Factories;

use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'email' => $this->faker->unique()->companyEmail(),
        ];
    }
}
