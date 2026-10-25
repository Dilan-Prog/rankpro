<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\CorreoPlantilla;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\ReporteProgramacion>
 */
class ReporteProgramacionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'area' => 'seo',
            'plantilla_id' => CorreoPlantilla::factory(),
            'asunto' => 'Tu reporte de {{mes}} — {{cliente}}',
            'destinatarios' => ['cliente@example.com'],
            'dia_mes' => 5,
            'hora' => '09:00',
            'activo' => true,
            'proximo_envio_en' => now()->subMinute(),
        ];
    }
}
