<?php

namespace Database\Factories;

use App\Models\AdsCampana;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AdsMetrica>
 */
class AdsMetricaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ads_campana_id' => AdsCampana::factory(),
            'cliente_id' => Cliente::factory(),
            'mes' => now()->month,
            'anio' => now()->year,
            'inversion_real' => $this->faker->randomFloat(2, 1000, 10000),
            'impresiones' => $this->faker->numberBetween(1000, 100000),
            'clics' => $this->faker->numberBetween(50, 5000),
            'ctr' => $this->faker->randomFloat(3, 0.5, 5),
            'cpc' => $this->faker->randomFloat(2, 1, 20),
            'conversiones' => $this->faker->numberBetween(1, 200),
            'cpl' => $this->faker->randomFloat(2, 10, 200),
            'cpa' => $this->faker->randomFloat(2, 10, 200),
            'roas' => $this->faker->randomFloat(2, 1, 8),
            'valor_conversion' => $this->faker->randomFloat(2, 1000, 20000),
        ];
    }
}
