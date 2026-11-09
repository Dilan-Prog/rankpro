<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Servicio>
 */
class ServicioFactory extends Factory
{
    public function unico(): static
    {
        return $this->state(['tipo_cobro' => 'unico']);
    }

    public function gratuito(): static
    {
        return $this->state(['tipo_cobro' => 'gratuito', 'precio_mensual' => 0]);
    }

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'tipo' => 'google_ads',
            'nombre' => 'Google Ads — ' . $this->faker->word(),
            'descripcion' => null,
            'tipo_cobro' => 'mensual',
            'precio_mensual' => $this->faker->randomFloat(2, 5000, 20000),
            'estado' => 'activo',
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => null,
        ];
    }
}
