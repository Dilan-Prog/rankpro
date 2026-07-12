<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\SeoCampana;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class SeoCampanaSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();

        $servicioDental = Servicio::where('cliente_id', $dental->id)->where('tipo', 'seo')->firstOrFail();
        $servicioAndes = Servicio::where('cliente_id', $andes->id)->where('tipo', 'seo')->firstOrFail();

        SeoCampana::updateOrCreate(
            ['cliente_id' => $dental->id, 'servicio_id' => $servicioDental->id],
            [
                'nombre' => 'SEO Dental Sonrisa 2025',
                'url_sitio' => 'https://dentalsonrisa.mx',
                'estado' => 'activa',
                'seo_score' => 78,
                'trafico_organico_mensual' => 5400,
                'backlinks_total' => 34,
                'errores_tecnicos' => 6,
                'velocidad_mobile' => 68.5,
                'velocidad_desktop' => 91.2,
                'sitemap_ok' => true,
                'robots_ok' => true,
                'notas' => 'Enfoque en keywords transaccionales de implantes y ortodoncia.',
                'fecha_inicio' => '2025-01-20',
            ]
        );

        SeoCampana::updateOrCreate(
            ['cliente_id' => $andes->id, 'servicio_id' => $servicioAndes->id],
            [
                'nombre' => 'SEO Institucional Andes',
                'url_sitio' => 'https://constructoraandes.mx',
                'estado' => 'activa',
                'seo_score' => 64,
                'trafico_organico_mensual' => 1850,
                'backlinks_total' => 12,
                'errores_tecnicos' => 14,
                'velocidad_mobile' => 55.0,
                'velocidad_desktop' => 82.4,
                'sitemap_ok' => true,
                'robots_ok' => false,
                'notas' => 'Sitio requiere optimización técnica prioritaria (robots.txt bloqueando recursos).',
                'fecha_inicio' => '2025-01-10',
            ]
        );
    }
}
