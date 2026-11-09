<?php

namespace App\Support\Plantillas;

use App\Enums\EstadoClienteServicio;
use App\Enums\EstadoKeyword;
use App\Enums\TipoCobro;
use App\Models\Cliente;
use App\Models\KeywordLista;
use App\Models\MetricaCliente;
use App\Models\Servicio;
use App\Models\ServicioEvento;
use App\Support\Keywords\ImportadorKeywords;
use App\Support\Metricas\Catalogo;
use App\Support\Reglas\Servicios as ReglasServicios;
use Illuminate\Support\Facades\DB;

/**
 * Aplica a un cliente lo que sugiere su giro: Servicios (los que el usuario dejó en el paso 3, con su cobro y precio),
 * métricas del catálogo (sin meta) y una lista de keywords semilla. Todo en UNA transacción y de forma idempotente:
 * lo que el cliente ya tiene (mismo tipo+nombre de servicio, misma métrica estándar, misma keyword) no se duplica.
 *
 * NO crea campañas SEO/Ads: exigen servicio_id y el SEO nace con 4 filas de fase; eso se decide al contratar.
 */
class AplicadorPlantilla
{
    public function __construct(private readonly ImportadorKeywords $importador)
    {
    }

    /**
     * @param  array<int, array{tipo: string, nombre: string, tipo_cobro?: string, precio_mensual?: float|int|string|null}>  $servicios
     * @param  string|null  $giro  clave de Giros; null = sin plantilla (solo se crean los servicios dados)
     * @param  ?string  $tema  servicio/producto/atracción para los marcadores de las keywords semilla
     * @return array{servicios_creados: int, servicios_existentes: int, metricas_creadas: int, metricas_existentes: int, lista_keywords_id: ?int, keywords_creadas: int, keywords_duplicadas: int, keywords_omitidas: array<int, string>}
     */
    public function aplicar(Cliente $cliente, ?string $giro, array $servicios = [], ?string $tema = null, ?int $usuarioId = null): array
    {
        $plantilla = Giros::buscar($giro);

        return DB::transaction(function () use ($cliente, $plantilla, $servicios, $tema, $usuarioId) {
            $resumen = ['servicios_creados' => 0, 'servicios_existentes' => 0, 'metricas_creadas' => 0, 'metricas_existentes' => 0,
                'lista_keywords_id' => null, 'keywords_creadas' => 0, 'keywords_duplicadas' => 0, 'keywords_omitidas' => []];

            $this->servicios($cliente, $servicios, $usuarioId, $resumen);

            if ($plantilla) {
                $this->metricas($cliente, $plantilla['metricas'], $resumen);
                $this->keywords($cliente, $plantilla, $tema, $resumen);
            }

            return $resumen;
        });
    }

    private function servicios(Cliente $cliente, array $servicios, ?int $usuarioId, array &$resumen): void
    {
        $existentes = Servicio::where('cliente_id', $cliente->id)->get(['tipo', 'nombre'])
            ->mapWithKeys(fn (Servicio $s) => [$this->claveServicio($s->tipo->value, $s->nombre) => true])->all();

        foreach ($servicios as $s) {
            $clave = $this->claveServicio($s['tipo'], $s['nombre']);
            if (isset($existentes[$clave])) {
                $resumen['servicios_existentes']++;

                continue;
            }
            $existentes[$clave] = true;

            $datos = ReglasServicios::normalizar([
                'cliente_id' => $cliente->id,
                'tipo' => $s['tipo'],
                'nombre' => $s['nombre'],
                'tipo_cobro' => $s['tipo_cobro'] ?? TipoCobro::Mensual->value,
                'precio_mensual' => $s['precio_mensual'] ?? null,
                'estado' => EstadoClienteServicio::Activo->value,
            ]);
            $servicio = Servicio::create($datos);
            ServicioEvento::create([
                'servicio_id' => $servicio->id,
                'usuario_id' => $usuarioId,
                'descripcion' => 'Servicio creado desde el asistente de alta.',
                'created_at' => now(),
            ]);
            $resumen['servicios_creados']++;
        }
    }

    private function claveServicio(string $tipo, string $nombre): string
    {
        return $tipo.'|'.mb_strtolower(trim($nombre));
    }

    /** Sin meta: no hay benchmarks verificables y la meta la fija el usuario (misma regla que "Agregar del catálogo"). */
    private function metricas(Cliente $cliente, array $claves, array &$resumen): void
    {
        $existentes = MetricaCliente::where('cliente_id', $cliente->id)->whereNotNull('metrica_estandar')->pluck('metrica_estandar')->all();
        $orden = (int) MetricaCliente::where('cliente_id', $cliente->id)->max('orden');

        foreach ($claves as $clave) {
            $entrada = Catalogo::buscar($clave);
            if (! $entrada) {
                continue;
            }
            if (in_array($clave, $existentes, true)) {
                $resumen['metricas_existentes']++;

                continue;
            }

            MetricaCliente::create([
                'cliente_id' => $cliente->id,
                'nombre' => $entrada['nombre'],
                'unidad' => $entrada['unidad'],
                'meta' => null,
                'sentido' => $entrada['sentido'],
                'fuente' => $entrada['fuente_tipica'],
                'metrica_estandar' => $clave,
                'umbral_amarillo_pct' => 80,
                'activa' => true,
                'orden' => ++$orden,
            ]);
            $resumen['metricas_creadas']++;
        }
    }

    private function keywords(Cliente $cliente, array $plantilla, ?string $tema, array &$resumen): void
    {
        $datos = ['ciudad' => $cliente->ciudad, $plantilla['tema']['marcador'] => $tema];
        $lineas = [];
        foreach ($plantilla['keywords'] as $modelo) {
            $keyword = Giros::rellenar($modelo, $datos);
            if ($keyword === null) {
                // Falta un dato del marcador: se avisa en el resumen en vez de inventar la keyword.
                $resumen['keywords_omitidas'][] = $modelo;

                continue;
            }
            $lineas[] = $keyword;
        }

        if ($lineas === []) {
            return;
        }

        $lista = KeywordLista::firstOrCreate(
            ['cliente_id' => $cliente->id, 'nombre' => 'Semilla — '.$plantilla['etiqueta']],
            ['canal' => 'seo', 'estado' => EstadoKeyword::EnUso]
        );
        $resultado = $this->importador->importar($lista, $lineas);

        $resumen['lista_keywords_id'] = $lista->id;
        $resumen['keywords_creadas'] = count($resultado['creadas']);
        $resumen['keywords_duplicadas'] = $resultado['duplicadas'];
    }
}
