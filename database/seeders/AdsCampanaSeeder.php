<?php

namespace Database\Seeders;

use App\Models\AdsCampana;
use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class AdsCampanaSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $servicioDental = Servicio::where('cliente_id', $dental->id)->where('tipo', 'google_ads')->firstOrFail();

        AdsCampana::updateOrCreate(
            ['cliente_id' => $dental->id, 'servicio_id' => $servicioDental->id, 'nombre' => 'Google Search — Implantes y Ortodoncia'],
            [
                'plataforma' => 'google_ads',
                'objetivo' => 'leads',
                'presupuesto_mensual' => 18000,
                'estado' => 'activa',
                'fecha_inicio' => '2025-01-20',
                'notas' => 'ROAS objetivo: 6x. Prioridad en keywords de implantes.',
            ]
        );

        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $servicioAurora = Servicio::where('cliente_id', $aurora->id)->where('tipo', 'meta_ads')->firstOrFail();

        AdsCampana::updateOrCreate(
            ['cliente_id' => $aurora->id, 'servicio_id' => $servicioAurora->id, 'nombre' => 'Meta Ads — Colección Primavera'],
            [
                'plataforma' => 'meta_ads',
                'objetivo' => 'ventas',
                'presupuesto_mensual' => 9500,
                'estado' => 'activa',
                'fecha_inicio' => '2025-03-05',
                'notas' => 'Enfoque en Instagram Stories y Reels. Audiencia 25-40 años.',
            ]
        );
    }
}
