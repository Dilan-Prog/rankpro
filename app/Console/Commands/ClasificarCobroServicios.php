<?php

namespace App\Console\Commands;

use App\Enums\TipoCobro;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reclasifica el tipo de cobro de los servicios que ya existían (todos nacieron "mensual" por el default
 * de la migración, lo que inflaba el MRR). Por defecto solo simula; --aplicar escribe en una transacción.
 *
 * Las reglas van por (empresa del cliente + nombre del servicio) y no por id: los ids de producción
 * pueden diferir de los locales. Es idempotente: una segunda corrida no encuentra nada que cambiar.
 */
class ClasificarCobroServicios extends Command
{
    protected $signature = 'servicios:clasificar-cobro {--aplicar : Escribe los cambios (sin esta opción solo lista lo que cambiaría)}';

    protected $description = 'Clasifica el tipo de cobro (mensual, único, gratuito) de los servicios existentes según una tabla de reglas';

    /**
     * Tabla de reglas decidida por el usuario. Empresa y nombre se comparan sin mayúsculas, acentos ni espacios repetidos.
     *
     * @return list<array{empresa: string, servicio: string, cobro: TipoCobro}>
     */
    public static function reglas(): array
    {
        return [
            // Hotel Fratelli
            ['empresa' => 'Hotel Fratelli', 'servicio' => 'Google Ads', 'cobro' => TipoCobro::Mensual],
            ['empresa' => 'Hotel Fratelli', 'servicio' => 'Dominio + Hosting', 'cobro' => TipoCobro::Mensual],
            ['empresa' => 'Hotel Fratelli', 'servicio' => 'Inversión Ads', 'cobro' => TipoCobro::Mensual],
            ['empresa' => 'Hotel Fratelli', 'servicio' => 'Página Web Hotel Fratelli', 'cobro' => TipoCobro::Unico],
            ['empresa' => 'Hotel Fratelli', 'servicio' => 'Campaña Hotel Fratelli Mes Gratuito', 'cobro' => TipoCobro::Unico],
            ['empresa' => 'Hotel Fratelli', 'servicio' => '1 URL de SEO Gratuita', 'cobro' => TipoCobro::Gratuito],
            // Hotel Finca del Minero
            ['empresa' => 'Hotel Finca del Minero', 'servicio' => 'Página Web Hotel La finca del minero', 'cobro' => TipoCobro::Unico],
            ['empresa' => 'Hotel Finca del Minero', 'servicio' => '1 URL de SEO Gratuita', 'cobro' => TipoCobro::Gratuito],
        ];
    }

    public function handle(): int
    {
        $aplicar = (bool) $this->option('aplicar');
        $reglas = collect(self::reglas())->mapWithKeys(
            fn (array $r) => [self::clave($r['empresa'], $r['servicio']) => $r['cobro']]
        );

        $cambios = [];
        $sinCambio = 0;
        $sinRegla = [];

        Servicio::with('cliente:id,nombre,empresa')->orderBy('id')->get()->each(function (Servicio $s) use ($reglas, &$cambios, &$sinCambio, &$sinRegla) {
            $empresa = $s->cliente?->nombre_comercial ?: '';
            $cobro = $reglas->get(self::clave($empresa, $s->nombre));

            if ($cobro === null) {
                $sinRegla[] = "#{$s->id} {$empresa} — {$s->nombre} (queda {$s->tipo_cobro->label()})";

                return;
            }
            if ($s->tipo_cobro === $cobro) {
                $sinCambio++;

                return;
            }
            $cambios[] = ['servicio' => $s, 'empresa' => $empresa, 'cobro' => $cobro];
        });

        if ($cambios) {
            $this->table(
                ['ID', 'Empresa', 'Servicio', 'Precio', 'Actual', 'Nuevo'],
                array_map(fn (array $c) => [
                    $c['servicio']->id, $c['empresa'], $c['servicio']->nombre,
                    number_format((float) $c['servicio']->precio_mensual, 2),
                    $c['servicio']->tipo_cobro->label(), $c['cobro']->label(),
                ], $cambios)
            );
        } else {
            $this->info('No hay servicios por reclasificar.');
        }

        foreach ($sinRegla as $linea) {
            $this->warn("Sin regla: {$linea}");
        }
        $this->line("Ya clasificados: {$sinCambio} · Por cambiar: ".count($cambios).' · Sin regla: '.count($sinRegla));

        if (! $aplicar) {
            $this->comment($cambios ? 'Simulación: no se escribió nada. Repite con --aplicar para guardar.' : 'Simulación: nada que escribir.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($cambios) {
            foreach ($cambios as $c) {
                /** @var Servicio $s */
                $s = $c['servicio'];
                $s->update(['tipo_cobro' => $c['cobro']->value]);
                ServicioEvento::create([
                    'servicio_id' => $s->id,
                    'usuario_id' => null,
                    'descripcion' => "Tipo de cobro clasificado como {$c['cobro']->label()} (servicios:clasificar-cobro).",
                    'created_at' => now(),
                ]);
            }
        });

        $this->info('Aplicado: '.count($cambios).' servicios reclasificados.');

        return self::SUCCESS;
    }

    /** Clave de comparación: minúsculas, sin acentos y con espacios colapsados. */
    private static function clave(string $empresa, string $servicio): string
    {
        $limpiar = fn (string $t) => preg_replace('/\s+/', ' ', trim(Str::lower(Str::ascii($t))));

        return $limpiar($empresa).'|'.$limpiar($servicio);
    }
}
