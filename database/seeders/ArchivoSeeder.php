<?php

namespace Database\Seeders;

use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;

class ArchivoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@rankpro.test')->first();

        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        Archivo::updateOrCreate(
            ['cliente_id' => $dental->id, 'nombre' => 'Contrato de Servicios 2025.pdf'],
            [
                'tipo' => 'contrato',
                'ruta_archivo' => 'clientes/dental-sonrisa/contrato-2025.pdf',
                'tamano' => 2516582,
                'extension' => 'pdf',
                'subido_por' => $admin?->id,
            ]
        );

        Archivo::updateOrCreate(
            ['cliente_id' => $dental->id, 'nombre' => 'Reporte SEO Mayo 2025.pdf'],
            [
                'tipo' => 'reporte',
                'ruta_archivo' => 'clientes/dental-sonrisa/reporte-seo-2025-05.pdf',
                'tamano' => 1887436,
                'extension' => 'pdf',
                'subido_por' => $admin?->id,
            ]
        );

        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        Archivo::updateOrCreate(
            ['cliente_id' => $aurora->id, 'nombre' => 'Propuesta Rediseño Tienda.pdf'],
            [
                'tipo' => 'propuesta',
                'ruta_archivo' => 'clientes/boutique-aurora/propuesta-rediseno.pdf',
                'tamano' => 4213112,
                'extension' => 'pdf',
                'subido_por' => $admin?->id,
            ]
        );

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();
        Archivo::updateOrCreate(
            ['cliente_id' => $andes->id, 'nombre' => 'Wireframes CRM v1.fig'],
            [
                'tipo' => 'diseno',
                'ruta_archivo' => 'clientes/constructora-andes/wireframes-crm-v1.fig',
                'tamano' => 8912455,
                'extension' => 'fig',
                'subido_por' => $admin?->id,
            ]
        );
    }
}
