<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\AdsCampana>
 */
class AdsCampanaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'servicio_id' => Servicio::factory(),
            'nombre' => 'Campaña ' . $this->faker->words(2, true),
            'plataforma' => 'google_ads',
            'objetivo' => 'ventas',
            'presupuesto_mensual' => $this->faker->randomFloat(2, 5000, 20000),
            'estado' => 'activa',
            'fase_actual' => 'reporte',
            'ciclo_actual' => 1,
            'fecha_inicio' => now()->subMonths(3),
            'fecha_fin' => null,
            'notas' => null,
        ];
    }
}
