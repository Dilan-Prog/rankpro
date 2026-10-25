<?php

namespace App\Console\Commands;

use App\Models\GscPropiedad;
use App\Services\SearchConsole\Sincronizador;
use App\Services\SearchConsole\SincronizacionEnCurso;
use Illuminate\Console\Command;

/** Sincroniza con Search Console las propiedades activas (programado de madrugada; también se puede lanzar a mano). */
class SincronizarSearchConsole extends Command
{
    protected $signature = 'gsc:sincronizar {--cliente= : ID de un cliente concreto} {--meses= : Meses a traer (1-16); por defecto 16 la primera vez y 3 después}';

    protected $description = 'Importa de Google Search Console los totales mensuales y las posiciones de las keywords del banco';

    public function handle(Sincronizador $sincronizador): int
    {
        $meses = $this->option('meses');
        if ($meses !== null && (! ctype_digit((string) $meses) || (int) $meses < 1 || (int) $meses > Sincronizador::MESES_COMPLETO)) {
            $this->error('--meses debe ser un número entre 1 y '.Sincronizador::MESES_COMPLETO.'.');

            return self::INVALID;
        }

        $propiedades = GscPropiedad::query()
            ->where('activa', true)
            ->when($this->option('cliente') !== null, fn ($q) => $q->where('cliente_id', (int) $this->option('cliente')))
            ->orderBy('id')
            ->get();

        if ($propiedades->isEmpty()) {
            $this->line('No hay propiedades activas de Search Console para sincronizar.');

            return self::SUCCESS;
        }

        $fallos = 0;
        foreach ($propiedades as $propiedad) {
            try {
                $r = $sincronizador->sincronizar($propiedad, $meses !== null ? (int) $meses : null);
            } catch (SincronizacionEnCurso $e) {
                $this->warn("Cliente {$propiedad->cliente_id}: {$e->getMessage()}");

                continue;
            }

            if ($r['ok']) {
                $this->info("Cliente {$propiedad->cliente_id} ({$propiedad->site_url}): {$r['mensaje']}");
            } else {
                $fallos++;
                $this->error("Cliente {$propiedad->cliente_id} ({$propiedad->site_url}): {$r['mensaje']}");
            }
        }

        return $fallos > 0 ? self::FAILURE : self::SUCCESS;
    }
}
