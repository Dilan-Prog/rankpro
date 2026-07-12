<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Database\Seeder;

class TareaSeeder extends Seeder
{
    public function run(): void
    {
        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $proyectoAurora = Proyecto::where('cliente_id', $aurora->id)->firstOrFail();

        $tareasAurora = [
            ['titulo' => 'Maquetar página de catálogo', 'prioridad' => 'alta', 'estado' => 'completada', 'dias' => -5],
            ['titulo' => 'Integrar pasarela de pago Stripe', 'prioridad' => 'alta', 'estado' => 'en_progreso', 'dias' => 7],
            ['titulo' => 'Optimizar imágenes de producto', 'prioridad' => 'media', 'estado' => 'pendiente', 'dias' => 14],
        ];

        foreach ($tareasAurora as $t) {
            $proyectoAurora->tareas()->updateOrCreate(
                ['cliente_id' => $aurora->id, 'titulo' => $t['titulo']],
                [
                    'descripcion' => null,
                    'prioridad' => $t['prioridad'],
                    'estado' => $t['estado'],
                    'fecha_limite' => now()->addDays($t['dias'])->toDateString(),
                ]
            );
        }

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();
        $proyectoAndes = Proyecto::where('cliente_id', $andes->id)->firstOrFail();

        $tareasAndes = [
            ['titulo' => 'Diseñar módulo de seguimiento de obras', 'prioridad' => 'alta', 'estado' => 'completada', 'dias' => -20],
            ['titulo' => 'Implementar autenticación por roles', 'prioridad' => 'alta', 'estado' => 'en_progreso', 'dias' => 10],
            ['titulo' => 'Endpoint de exportación de reportes PDF', 'prioridad' => 'baja', 'estado' => 'pendiente', 'dias' => 30],
        ];

        foreach ($tareasAndes as $t) {
            $proyectoAndes->tareas()->updateOrCreate(
                ['cliente_id' => $andes->id, 'titulo' => $t['titulo']],
                [
                    'descripcion' => null,
                    'prioridad' => $t['prioridad'],
                    'estado' => $t['estado'],
                    'fecha_limite' => now()->addDays($t['dias'])->toDateString(),
                ]
            );
        }
    }
}
