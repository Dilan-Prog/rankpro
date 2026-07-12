<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Database\Seeder;

class BugSeeder extends Seeder
{
    public function run(): void
    {
        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();
        $proyectoAndes = Proyecto::where('cliente_id', $andes->id)->firstOrFail();

        $proyectoAndes->bugs()->updateOrCreate(
            ['titulo' => 'Error al exportar reporte de obra en PDF'],
            [
                'descripcion' => 'El endpoint de exportación devuelve 500 cuando la obra tiene más de 50 registros de avance.',
                'prioridad' => 'alta',
                'estado' => 'en_progreso',
                'fecha_resolucion' => null,
            ]
        );

        $proyectoAndes->bugs()->updateOrCreate(
            ['titulo' => 'Timeout en carga de historial de obras'],
            [
                'descripcion' => 'Consulta sin índice en tabla de avances históricos, tarda más de 10s con datos reales.',
                'prioridad' => 'media',
                'estado' => 'abierto',
                'fecha_resolucion' => null,
            ]
        );

        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $proyectoAurora = Proyecto::where('cliente_id', $aurora->id)->firstOrFail();

        $proyectoAurora->bugs()->updateOrCreate(
            ['titulo' => 'Menú móvil no cierra al seleccionar categoría'],
            [
                'descripcion' => 'En iOS Safari el menú permanece abierto tras navegar a una categoría del catálogo.',
                'prioridad' => 'baja',
                'estado' => 'resuelto',
                'fecha_resolucion' => now()->subDays(4)->toDateString(),
            ]
        );
    }
}
