<?php

namespace Database\Factories;

use App\Enums\EstadoReunion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Reunion>
 */
class ReunionFactory extends Factory
{
    public function definition(): array
    {
        $inicia = now()->addDay()->setTime(10, 0);

        return [
            'cliente_id' => null,
            'nombre' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'telefono' => $this->faker->phoneNumber(),
            'notas' => null,
            'inicia_en' => $inicia,
            'termina_en' => $inicia->copy()->addMinutes(30),
            'estado' => EstadoReunion::Confirmada,
        ];
    }
}
