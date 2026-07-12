<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\SeoCampana;
use Illuminate\Database\Seeder;

class SeoPosicionSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $campanaDental = SeoCampana::where('cliente_id', $dental->id)->firstOrFail();

        $posicionesDental = [
            ['keyword' => 'dentista cdmx', 'url' => '/dentista-cdmx', 'actual' => 3, 'anterior' => 5, 'volumen' => 8100, 'dificultad' => 42, 'dispositivo' => 'mobile'],
            ['keyword' => 'implantes dentales precio', 'url' => '/implantes-dentales', 'actual' => 7, 'anterior' => 12, 'volumen' => 3600, 'dificultad' => 38, 'dispositivo' => 'desktop'],
            ['keyword' => 'ortodoncia invisible cdmx', 'url' => '/ortodoncia-invisible', 'actual' => 6, 'anterior' => 9, 'volumen' => 2200, 'dificultad' => 45, 'dispositivo' => 'mobile'],
            ['keyword' => 'blanqueamiento dental precio', 'url' => '/blanqueamiento', 'actual' => 11, 'anterior' => 15, 'volumen' => 2900, 'dificultad' => 33, 'dispositivo' => 'mobile'],
        ];

        foreach ($posicionesDental as $p) {
            $campanaDental->posiciones()->updateOrCreate(
                ['cliente_id' => $dental->id, 'keyword' => $p['keyword']],
                [
                    'url_pagina' => $p['url'],
                    'posicion_actual' => $p['actual'],
                    'posicion_anterior' => $p['anterior'],
                    'variacion' => $p['anterior'] - $p['actual'],
                    'volumen_busqueda' => $p['volumen'],
                    'dificultad_keyword' => $p['dificultad'],
                    'dispositivo' => $p['dispositivo'],
                    'pais' => 'MX',
                    'fecha_registro' => now()->subDays(3)->toDateString(),
                ]
            );
        }

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();
        $campanaAndes = SeoCampana::where('cliente_id', $andes->id)->firstOrFail();

        $posicionesAndes = [
            ['keyword' => 'constructora cdmx', 'url' => '/', 'actual' => 14, 'anterior' => 22, 'volumen' => 1900, 'dificultad' => 55, 'dispositivo' => 'desktop'],
            ['keyword' => 'construccion de naves industriales', 'url' => '/naves-industriales', 'actual' => 9, 'anterior' => 9, 'volumen' => 720, 'dificultad' => 40, 'dispositivo' => 'desktop'],
        ];

        foreach ($posicionesAndes as $p) {
            $campanaAndes->posiciones()->updateOrCreate(
                ['cliente_id' => $andes->id, 'keyword' => $p['keyword']],
                [
                    'url_pagina' => $p['url'],
                    'posicion_actual' => $p['actual'],
                    'posicion_anterior' => $p['anterior'],
                    'variacion' => $p['anterior'] - $p['actual'],
                    'volumen_busqueda' => $p['volumen'],
                    'dificultad_keyword' => $p['dificultad'],
                    'dispositivo' => $p['dispositivo'],
                    'pais' => 'MX',
                    'fecha_registro' => now()->subDays(3)->toDateString(),
                ]
            );
        }
    }
}
