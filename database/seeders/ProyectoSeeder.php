<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Database\Seeder;

class ProyectoSeeder extends Seeder
{
    public function run(): void
    {
        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();

        Proyecto::updateOrCreate(
            ['cliente_id' => $aurora->id, 'nombre' => 'Rediseño Tienda en Línea Aurora'],
            [
                'tipo' => 'rediseno',
                'tecnologias' => ['Shopify', 'Liquid', 'Tailwind CSS'],
                'fecha_inicio' => '2025-04-01',
                'fecha_entrega_estimada' => '2025-08-01',
                'fecha_entrega_real' => null,
                'presupuesto' => 85000,
                'pagos_recibidos' => 42500,
                'estado' => 'frontend',
                'url_repositorio' => 'https://github.com/rankpro/aurora-tienda',
                'url_staging' => 'https://staging.boutiqueaurora.mx',
                'url_produccion' => null,
                'notas' => 'Fase de maquetación de catálogo en curso. Pendiente integración de pasarela de pago.',
            ]
        );

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();

        Proyecto::updateOrCreate(
            ['cliente_id' => $andes->id, 'nombre' => 'CRM Constructora Andes'],
            [
                'tipo' => 'software',
                'tecnologias' => ['Laravel', 'MySQL', 'Vue.js'],
                'fecha_inicio' => '2024-11-01',
                'fecha_entrega_estimada' => '2025-10-31',
                'fecha_entrega_real' => null,
                'presupuesto' => 480000,
                'pagos_recibidos' => 240000,
                'estado' => 'backend',
                'url_repositorio' => 'https://github.com/rankpro/andes-crm',
                'url_staging' => 'https://staging.crm-andes.mx',
                'url_produccion' => null,
                'notas' => 'Proyecto de 12 meses. Cliente estratégico, revisión quincenal de avance.',
            ]
        );
    }
}
