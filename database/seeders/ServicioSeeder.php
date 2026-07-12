<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class ServicioSeeder extends Seeder
{
    public function run(): void
    {
        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();

        Servicio::updateOrCreate(
            ['cliente_id' => $dental->id, 'tipo' => 'seo'],
            [
                'nombre' => 'SEO Local Odontología',
                'descripcion' => 'Posicionamiento orgánico enfocado en búsquedas locales de servicios dentales.',
                'precio_mensual' => 12000,
                'estado' => 'activo',
                'fecha_inicio' => '2025-01-15',
            ]
        );

        Servicio::updateOrCreate(
            ['cliente_id' => $dental->id, 'tipo' => 'google_ads'],
            [
                'nombre' => 'Google Ads — Captación de Pacientes',
                'descripcion' => 'Campañas de búsqueda para generación de citas.',
                'precio_mensual' => 15000,
                'estado' => 'activo',
                'fecha_inicio' => '2025-01-15',
            ]
        );

        Servicio::updateOrCreate(
            ['cliente_id' => $aurora->id, 'tipo' => 'meta_ads'],
            [
                'nombre' => 'Meta Ads — Colección Primavera',
                'descripcion' => 'Campañas de conversión en Instagram y Facebook.',
                'precio_mensual' => 9500,
                'estado' => 'activo',
                'fecha_inicio' => '2025-03-01',
            ]
        );

        Servicio::updateOrCreate(
            ['cliente_id' => $aurora->id, 'tipo' => 'rediseno'],
            [
                'nombre' => 'Rediseño Tienda en Línea',
                'descripcion' => 'Rediseño completo del e-commerce en Shopify.',
                'precio_mensual' => 0,
                'estado' => 'activo',
                'fecha_inicio' => '2025-04-01',
                'fecha_fin' => '2025-08-01',
            ]
        );

        Servicio::updateOrCreate(
            ['cliente_id' => $andes->id, 'tipo' => 'software'],
            [
                'nombre' => 'CRM a Medida',
                'descripcion' => 'Desarrollo de CRM interno para seguimiento de obras y clientes.',
                'precio_mensual' => 0,
                'estado' => 'activo',
                'fecha_inicio' => '2024-11-01',
            ]
        );

        Servicio::updateOrCreate(
            ['cliente_id' => $andes->id, 'tipo' => 'seo'],
            [
                'nombre' => 'SEO Institucional',
                'descripcion' => 'Posicionamiento de marca y captación de proyectos comerciales.',
                'precio_mensual' => 10000,
                'estado' => 'activo',
                'fecha_inicio' => '2025-01-01',
            ]
        );
    }
}
