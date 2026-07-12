<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Order matters: permissions/roles before users (role_id FK), clientes
     * before every table that references cliente_id, servicios before the
     * campaign tables that reference servicio_id, and campaign tables
     * before the records that hang off them (posiciones, keywords, ...).
     */
    public function run(): void
    {
        $this->call([
            // Auth / RBAC
            PermissionSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,

            // Core
            ClienteSeeder::class,
            ServicioSeeder::class,

            // SEO domain
            SeoCampanaSeeder::class,
            SeoPosicionSeeder::class,
            KeywordSeeder::class,
            BacklinkSeeder::class,

            // Ads domain
            AdsCampanaSeeder::class,
            AdsMetricaSeeder::class,
            AdsCreativoSeeder::class,

            // Desarrollo domain
            ProyectoSeeder::class,
            TareaSeeder::class,
            BugSeeder::class,

            // Finanzas / Archivos
            FinanzaSeeder::class,
            ArchivoSeeder::class,
        ]);
    }
}
