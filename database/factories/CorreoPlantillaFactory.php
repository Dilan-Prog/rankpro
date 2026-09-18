<?php

namespace Database\Factories;

use App\Support\Correo\Bloques;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CorreoPlantilla>
 */
class CorreoPlantillaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => 'Reporte mensual '.$this->faker->word(),
            'categoria' => 'reportes',
            'estado' => 'activa',
            'asunto' => 'Tu reporte de {{mes}} — {{cliente}}',
            'bloques' => Bloques::porDefecto(),
            'marca' => Bloques::marcaPorDefecto(),
            'html_personalizado' => null,
        ];
    }
}
