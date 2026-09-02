<?php

namespace Database\Seeders;

use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class ArchivoSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@rankpro.test')->first();

        $dental = Cliente::where('nombre', 'Clínica Dental Sonrisa')->firstOrFail();
        $this->archivo($dental->id, $admin?->id, 'Contrato de Servicios 2025.pdf', 'contrato', 'clientes/dental-sonrisa/contrato-2025.pdf', 'pdf');
        $this->archivo($dental->id, $admin?->id, 'Reporte SEO Mayo 2025.pdf', 'reporte', 'clientes/dental-sonrisa/reporte-seo-2025-05.pdf', 'pdf');

        $aurora = Cliente::where('nombre', 'Boutique Aurora')->firstOrFail();
        $this->archivo($aurora->id, $admin?->id, 'Propuesta Rediseño Tienda.pdf', 'propuesta', 'clientes/boutique-aurora/propuesta-rediseno.pdf', 'pdf');

        $andes = Cliente::where('nombre', 'Constructora Andes')->firstOrFail();
        $this->archivo($andes->id, $admin?->id, 'Wireframes CRM v1.fig', 'diseno', 'clientes/constructora-andes/wireframes-crm-v1.fig', 'fig');
        $this->archivo($andes->id, $admin?->id, 'Inventario de materiales.xlsx', 'datos', 'clientes/constructora-andes/inventario-materiales.xlsx', 'xlsx');
    }

    /**
     * Writes a small real placeholder file to the 'local' disk at $ruta and
     * stores its real byte size — unlike the old seeder's hardcoded fake
     * 'tamano' values pointing at paths with nothing behind them (which made
     * every seeded record download-fail with ArchivosController::download()'s
     * "registro de ejemplo" guard), this makes seeded data actually
     * downloadable end-to-end.
     */
    private function archivo(int $clienteId, ?int $subidoPor, string $nombre, string $tipo, string $ruta, string $extension): void
    {
        if (! Storage::disk('local')->exists($ruta)) {
            Storage::disk('local')->put($ruta, "Documento de ejemplo generado por ArchivoSeeder.\nCliente #{$clienteId} — {$nombre}\n");
        }

        Archivo::updateOrCreate(
            ['cliente_id' => $clienteId, 'nombre' => $nombre],
            [
                'tipo' => $tipo,
                'ruta_archivo' => $ruta,
                'tamano' => Storage::disk('local')->size($ruta),
                'extension' => $extension,
                'subido_por' => $subidoPor,
            ]
        );
    }
}
