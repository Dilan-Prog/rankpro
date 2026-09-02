<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Cliente>
 */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => $this->faker->company(),
            'empresa' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefono' => $this->faker->phoneNumber(),
            'contacto_nombre' => $this->faker->name(),
            'estado' => 'activo',
            'fecha_inicio_contrato' => now()->subMonths(6),
            'fecha_renovacion_contrato' => now()->addMonths(6),
            'forma_pago' => 'mensual',
            'metodo_pago' => 'transferencia',
            'notas' => null,
        ];
    }
}
