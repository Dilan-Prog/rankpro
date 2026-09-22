<?php

namespace Database\Factories;

use App\Models\CorreoEnvio;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CorreoAdjunto>
 */
class CorreoAdjuntoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'envio_id' => CorreoEnvio::factory(),
            'nombre' => $this->faker->word().'.pdf',
            'ruta' => 'correo/envios/factory/'.$this->faker->uuid().'.pdf',
            'disco' => 'local',
            'mime' => 'application/pdf',
            'tamano' => $this->faker->numberBetween(100, 5000),
        ];
    }
}
