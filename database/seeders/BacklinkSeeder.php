<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\SeoCampana;
use Illuminate\Database\Seeder;

class BacklinkSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $campanaDental = SeoCampana::where('cliente_id', $dental->id)->firstOrFail();

        $backlinks = [
            ['origen' => 'saluddental.com.mx', 'destino' => '/implantes-dentales', 'da_dr' => 48, 'tipo' => 'dofollow', 'estado' => 'activo'],
            ['origen' => 'guiacdmx.mx', 'destino' => '/dentista-cdmx', 'da_dr' => 52, 'tipo' => 'dofollow', 'estado' => 'activo'],
            ['origen' => 'blog.saludbucal.mx', 'destino' => '/blanqueamiento', 'da_dr' => 29, 'tipo' => 'nofollow', 'estado' => 'caido'],
        ];

        foreach ($backlinks as $b) {
            $campanaDental->backlinks()->updateOrCreate(
                ['cliente_id' => $dental->id, 'url_origen' => $b['origen'], 'url_destino' => $b['destino']],
                [
                    'da_dr' => $b['da_dr'],
                    'tipo' => $b['tipo'],
                    'estado' => $b['estado'],
                    'fecha_conseguido' => now()->subMonths(2)->toDateString(),
                ]
            );
        }
    }
}
