<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Servicio>
 */
class ServicioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'tipo' => 'google_ads',
            'nombre' => 'Google Ads — ' . $this->faker->word(),
            'descripcion' => null,
            'precio_mensual' => $this->faker->randomFloat(2, 5000, 20000),
            'estado' => 'activo',
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => null,
        ];
    }
}
