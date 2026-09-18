<?php

namespace Database\Factories;

use App\Models\CorreoPlantilla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\CorreoEnvio>
 */
class CorreoEnvioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'plantilla_id' => CorreoPlantilla::factory(),
            'asunto' => 'Tu reporte de {{mes}}',
            'remitente_nombre' => 'RankPro',
            'remitente_email' => 'administracion@rankprosolutions.com.mx',
            'estado' => 'borrador',
            'variables' => ['mes' => 'agosto 2026', 'servicio' => 'SEO'],
        ];
    }
}
