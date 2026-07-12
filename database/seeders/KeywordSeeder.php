<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Keyword;
use App\Models\SeoCampana;
use Illuminate\Database\Seeder;

class KeywordSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $campanaDental = SeoCampana::where('cliente_id', $dental->id)->first();

        $keywordsDental = [
            ['keyword' => 'dentista cdmx', 'tipo' => 'principal', 'volumen' => 8100, 'dificultad' => 42, 'cpc' => 45.20, 'intencion' => 'transaccional', 'url' => '/dentista-cdmx', 'posicion' => 3, 'estado' => 'en_uso'],
            ['keyword' => 'blanqueamiento dental precio', 'tipo' => 'secundaria', 'volumen' => 3600, 'dificultad' => 38, 'cpc' => 32.50, 'intencion' => 'transaccional', 'url' => '/blanqueamiento', 'posicion' => 11, 'estado' => 'en_uso'],
            ['keyword' => 'ortodoncia invisible precio cdmx', 'tipo' => 'long_tail', 'volumen' => 2200, 'dificultad' => 45, 'cpc' => 38.00, 'intencion' => 'transaccional', 'url' => '/ortodoncia-invisible', 'posicion' => 6, 'estado' => 'en_uso'],
            ['keyword' => 'que hacer antes de un implante dental', 'tipo' => 'lsi', 'volumen' => 590, 'dificultad' => 22, 'cpc' => 8.10, 'intencion' => 'informacional', 'url' => null, 'posicion' => null, 'estado' => 'seguimiento'],
        ];

        foreach ($keywordsDental as $k) {
            Keyword::updateOrCreate(
                ['cliente_id' => $dental->id, 'keyword' => $k['keyword']],
                [
                    'campana_id' => $campanaDental?->id,
                    'tipo' => $k['tipo'],
                    'volumen_busqueda' => $k['volumen'],
                    'dificultad' => $k['dificultad'],
                    'cpc_estimado' => $k['cpc'],
                    'intencion' => $k['intencion'],
                    'idioma' => 'es',
                    'pais' => 'MX',
                    'herramienta_origen' => 'semrush',
                    'url_asignada' => $k['url'],
                    'posicion_actual' => $k['posicion'],
                    'estado' => $k['estado'],
                    'fecha_incorporacion' => '2025-01-20',
                    'notas' => null,
                ]
            );
        }

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();
        $campanaAndes = SeoCampana::where('cliente_id', $andes->id)->first();

        Keyword::updateOrCreate(
            ['cliente_id' => $andes->id, 'keyword' => 'constructora cdmx'],
            [
                'campana_id' => $campanaAndes?->id,
                'tipo' => 'principal',
                'volumen_busqueda' => 1900,
                'dificultad' => 55,
                'cpc_estimado' => 62.00,
                'intencion' => 'transaccional',
                'idioma' => 'es',
                'pais' => 'MX',
                'herramienta_origen' => 'ahrefs',
                'url_asignada' => '/',
                'posicion_actual' => 14,
                'estado' => 'en_uso',
                'fecha_incorporacion' => '2025-01-10',
                'notas' => null,
            ]
        );

        // Cliente sin campaña SEO activa: keyword de investigación, aún sin asignar.
        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        Keyword::updateOrCreate(
            ['cliente_id' => $aurora->id, 'keyword' => 'vestidos de noche 2025'],
            [
                'campana_id' => null,
                'tipo' => 'secundaria',
                'volumen_busqueda' => 4400,
                'dificultad' => 35,
                'cpc_estimado' => 8.90,
                'intencion' => 'informacional',
                'idioma' => 'es',
                'pais' => 'MX',
                'herramienta_origen' => 'google_kp',
                'url_asignada' => null,
                'posicion_actual' => null,
                'estado' => 'seguimiento',
                'fecha_incorporacion' => '2025-05-02',
                'notas' => 'Candidata para futura estrategia de contenido SEO.',
            ]
        );
    }
}
