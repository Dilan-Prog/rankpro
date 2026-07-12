<?php

namespace Database\Seeders;

use App\Models\AdsCampana;
use Illuminate\Database\Seeder;

class AdsMetricaSeeder extends Seeder
{
    /**
     * Seeds the last 3 months of metrics for every ads campaign.
     */
    public function run(): void
    {
        $campanas = AdsCampana::all();

        foreach ($campanas as $campana) {
            $base = [
                'impresiones' => random_int(180000, 650000),
                'clics' => random_int(4500, 16000),
            ];

            for ($i = 2; $i >= 0; $i--) {
                $date = now()->subMonths($i);
                $impresiones = (int) ($base['impresiones'] * (1 + (2 - $i) * 0.08));
                $clics = (int) ($base['clics'] * (1 + (2 - $i) * 0.05));
                $inversion = round($campana->presupuesto_mensual * (0.85 + $i * 0.03), 2);
                $conversiones = random_int(60, 260);
                $cpc = $clics > 0 ? round($inversion / $clics, 2) : 0;
                $cpl = $conversiones > 0 ? round($inversion / $conversiones, 2) : 0;
                $valorConversion = round($conversiones * random_int(800, 2200), 2);
                $roas = $inversion > 0 ? round($valorConversion / $inversion, 2) : 0;

                $campana->metricas()->updateOrCreate(
                    ['cliente_id' => $campana->cliente_id, 'mes' => $date->month, 'anio' => $date->year],
                    [
                        'inversion_real' => $inversion,
                        'impresiones' => $impresiones,
                        'clics' => $clics,
                        'ctr' => $impresiones > 0 ? round(($clics / $impresiones) * 100, 3) : 0,
                        'cpc' => $cpc,
                        'conversiones' => $conversiones,
                        'cpl' => $cpl,
                        'cpa' => $cpl,
                        'roas' => $roas,
                        'valor_conversion' => $valorConversion,
                    ]
                );
            }
        }
    }
}
