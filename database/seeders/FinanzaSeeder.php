<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class FinanzaSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $servicioSeoDental = Servicio::where('cliente_id', $dental->id)->where('tipo', 'seo')->firstOrFail();

        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $servicioAdsAurora = Servicio::where('cliente_id', $aurora->id)->where('tipo', 'meta_ads')->firstOrFail();

        // 3 months of recurring paid invoices so the Finanzas revenue chart has a trend.
        foreach ([4 => 'Abril', 5 => 'Mayo'] as $mes => $mesLabel) {
            Finanza::updateOrCreate(
                ['cliente_id' => $dental->id, 'servicio_id' => $servicioSeoDental->id, 'concepto' => "SEO + Google Ads — {$mesLabel} 2025"],
                [
                    'tipo' => 'ingreso',
                    'monto' => 27000,
                    'estado' => 'pagado',
                    'fecha_emision' => "2025-{$mes}-01",
                    'fecha_vencimiento' => "2025-{$mes}-05",
                    'fecha_pago' => "2025-{$mes}-03",
                    'mes' => $mes,
                    'anio' => 2025,
                    'notas' => null,
                ]
            );

            Finanza::updateOrCreate(
                ['cliente_id' => $aurora->id, 'servicio_id' => $servicioAdsAurora->id, 'concepto' => "Meta Ads — {$mesLabel} 2025"],
                [
                    'tipo' => 'ingreso',
                    'monto' => 9500,
                    'estado' => 'pagado',
                    'fecha_emision' => "2025-{$mes}-01",
                    'fecha_vencimiento' => "2025-{$mes}-15",
                    'fecha_pago' => "2025-{$mes}-10",
                    'mes' => $mes,
                    'anio' => 2025,
                    'notas' => null,
                ]
            );

            Finanza::updateOrCreate(
                ['cliente_id' => $dental->id, 'servicio_id' => null, 'concepto' => "Licencias y herramientas SEO — {$mesLabel} 2025"],
                [
                    'tipo' => 'gasto',
                    'monto' => 1800,
                    'estado' => 'pagado',
                    'fecha_emision' => "2025-{$mes}-01",
                    'fecha_vencimiento' => "2025-{$mes}-01",
                    'fecha_pago' => "2025-{$mes}-01",
                    'mes' => $mes,
                    'anio' => 2025,
                    'notas' => 'Gasto interno de agencia (Semrush/Ahrefs), no facturado al cliente.',
                ]
            );
        }

        Finanza::updateOrCreate(
            ['cliente_id' => $dental->id, 'servicio_id' => $servicioSeoDental->id, 'concepto' => 'SEO + Google Ads — Junio 2025'],
            [
                'tipo' => 'ingreso',
                'monto' => 27000,
                'estado' => 'pagado',
                'fecha_emision' => '2025-06-01',
                'fecha_vencimiento' => '2025-06-05',
                'fecha_pago' => '2025-06-03',
                'mes' => 6,
                'anio' => 2025,
                'notas' => null,
            ]
        );

        Finanza::updateOrCreate(
            ['cliente_id' => $aurora->id, 'servicio_id' => $servicioAdsAurora->id, 'concepto' => 'Meta Ads — Junio 2025'],
            [
                'tipo' => 'ingreso',
                'monto' => 9500,
                'estado' => 'pendiente',
                'fecha_emision' => '2025-06-01',
                'fecha_vencimiento' => '2025-06-15',
                'fecha_pago' => null,
                'mes' => 6,
                'anio' => 2025,
                'notas' => null,
            ]
        );

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();

        Finanza::updateOrCreate(
            ['cliente_id' => $andes->id, 'servicio_id' => null, 'concepto' => 'Desarrollo CRM — Pago 8/12'],
            [
                'tipo' => 'ingreso',
                'monto' => 40000,
                'estado' => 'vencido',
                'fecha_emision' => '2025-06-01',
                'fecha_vencimiento' => '2025-06-05',
                'fecha_pago' => null,
                'mes' => 6,
                'anio' => 2025,
                'notas' => 'Recordatorio enviado 2 veces, sin respuesta del cliente.',
            ]
        );

        Finanza::updateOrCreate(
            ['cliente_id' => $andes->id, 'servicio_id' => null, 'concepto' => 'Licencias de hosting y dominios'],
            [
                'tipo' => 'gasto',
                'monto' => 2400,
                'estado' => 'pagado',
                'fecha_emision' => '2025-06-01',
                'fecha_vencimiento' => '2025-06-01',
                'fecha_pago' => '2025-06-01',
                'mes' => 6,
                'anio' => 2025,
                'notas' => 'Gasto interno de agencia, no facturado al cliente.',
            ]
        );
    }
}
