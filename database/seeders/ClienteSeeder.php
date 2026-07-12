<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        Cliente::updateOrCreate(['nombre' => 'Clínica Dental Sonrisa'], [
            'empresa' => 'Clínica Dental Sonrisa S.A. de C.V.',
            'email' => 'contacto@dentalsonrisa.mx',
            'telefono' => '5215512345678',
            'contacto_nombre' => 'Dra. Mariana López',
            'estado' => 'activo',
            'fecha_inicio_contrato' => '2025-01-15',
            'fecha_renovacion_contrato' => '2026-01-15',
            'forma_pago' => 'mensual',
            'metodo_pago' => 'transferencia',
            'notas' => 'Cliente premium. Reporte semanal de posiciones SEO y reunión mensual de resultados.',
        ]);

        Cliente::updateOrCreate(['nombre' => 'Boutique Aurora'], [
            'empresa' => 'Boutique Aurora Moda S.A. de C.V.',
            'email' => 'hola@boutiqueaurora.mx',
            'telefono' => '5215587654321',
            'contacto_nombre' => 'Fernanda Castillo',
            'estado' => 'activo',
            'fecha_inicio_contrato' => '2025-03-01',
            'fecha_renovacion_contrato' => '2025-12-01',
            'forma_pago' => 'trimestral',
            'metodo_pago' => 'tarjeta',
            'notas' => 'Enfoque en Instagram/Facebook Ads. Rediseño de tienda en línea en curso.',
        ]);

        Cliente::updateOrCreate(['nombre' => 'Constructora Andes'], [
            'empresa' => 'Grupo Constructora Andes S.A. de C.V.',
            'email' => 'administracion@constructoraandes.mx',
            'telefono' => '5215598765432',
            'contacto_nombre' => 'Ing. Roberto Salinas',
            'estado' => 'activo',
            'fecha_inicio_contrato' => '2024-11-01',
            'fecha_renovacion_contrato' => '2025-11-01',
            'forma_pago' => 'mensual',
            'metodo_pago' => 'transferencia',
            'notas' => 'CRM a medida en desarrollo. Prioridad alta, cliente estratégico.',
        ]);
    }
}
