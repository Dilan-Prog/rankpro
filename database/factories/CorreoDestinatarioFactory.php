<?php

namespace Database\Factories;

use App\Models\CorreoEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CorreoDestinatario>
 */
class CorreoDestinatarioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'envio_id' => CorreoEnvio::factory(),
            'email' => $this->faker->unique()->safeEmail(),
            'nombre' => $this->faker->name(),
            'estado' => 'pendiente',
        ];
    }
}
