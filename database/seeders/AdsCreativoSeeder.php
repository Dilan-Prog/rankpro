<?php

namespace Database\Seeders;

use App\Models\AdsCampana;
use App\Models\Cliente;
use Illuminate\Database\Seeder;

class AdsCreativoSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $campanaDental = AdsCampana::where('cliente_id', $dental->id)->firstOrFail();

        $campanaDental->creativos()->updateOrCreate(
            ['titulo' => 'Implantes dentales — Primera consulta gratis'],
            [
                'copy' => 'Recupera tu sonrisa con implantes de titanio premium. Agenda tu evaluación sin costo hoy mismo.',
                'tipo' => 'imagen',
                'url_imagen' => 'https://cdn.rankpro.mx/creativos/dental-implantes-01.jpg',
                'ctr' => 2.8,
                'estado' => 'activo',
                'ab_testing' => true,
            ]
        );

        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $campanaAurora = AdsCampana::where('cliente_id', $aurora->id)->firstOrFail();

        $campanaAurora->creativos()->updateOrCreate(
            ['titulo' => 'Colección Primavera — Nueva llegada'],
            [
                'copy' => 'Descubre los nuevos looks de temporada. Envío gratis en compras mayores a $999.',
                'tipo' => 'carrusel',
                'url_imagen' => 'https://cdn.rankpro.mx/creativos/aurora-primavera-01.jpg',
                'ctr' => 3.4,
                'estado' => 'activo',
                'ab_testing' => false,
            ]
        );
    }
}
