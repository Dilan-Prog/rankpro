<?php

namespace App\Support\Tablero;

use App\Enums\TipoArchivo;
use App\Enums\TipoCobro;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Propuesta;
use App\Models\Reporte;
use App\Models\Servicio;
use App\Models\User;
use App\Support\FinanzasMetrics;
use App\Support\Labels;

/**
 * Ficha mínima del cliente (datos, contrato, servicios con su cobro, últimos reportes y propuestas, contratos
 * en PDF y enlaces a integraciones). Solo lectura. Cada sección respeta el permiso de su módulo: un usuario con
 * `clientes` pero sin `reportes` no ve la lista de reportes, igual que no podría abrirla desde el menú.
 */
class FichaCliente
{
    /** Cuántos reportes, propuestas y archivos se listan; el resto está en su módulo. */
    public const FILAS = 5;

    /**
     * @return array<string, mixed>
     */
    public function datos(Cliente $cliente, ?User $usuario = null): array
    {
        $puede = fn (string $modulo): bool => $usuario === null || $usuario->tienePermiso($modulo);

        $contacto = filled($cliente->contacto_nombre)
            ? $cliente->contacto_nombre
            : ($cliente->nombre !== $cliente->nombre_comercial ? $cliente->nombre : null);

        $ficha = [
            'nombre' => $cliente->nombre_comercial,
            // El contacto es dato SECUNDARIO: la identidad del cliente en el panel es la empresa.
            'contacto' => $contacto,
            'email' => $cliente->email,
            'telefono' => $cliente->telefono,
            'estado' => $cliente->estado,
            'contrato' => [
                'inicio' => $cliente->fecha_inicio_contrato?->format('d/m/Y'),
                'renovacion' => $cliente->fecha_renovacion_contrato?->format('d/m/Y'),
                'forma_pago' => $cliente->forma_pago ? ucfirst($cliente->forma_pago->value) : null,
                'metodo_pago' => $cliente->metodo_pago ? ucfirst($cliente->metodo_pago->value) : null,
            ],
            'notas' => $cliente->notas,
            'servicios' => null,
            'reportes' => null,
            'propuestas' => null,
            'archivos' => null,
            'integraciones' => $this->integraciones($cliente, $puede),
        ];

        // Precios y MRR son dinero: se ven con Servicios o con Finanzas.
        if ($puede('servicios') || $puede('finanzas')) {
            $ficha['servicios'] = $this->servicios($cliente);
        }

        if ($puede('reportes')) {
            $ficha['reportes'] = Reporte::query()
                ->where('cliente_id', $cliente->id)
                ->orderByDesc('fecha_emision')->orderByDesc('id')
                ->limit(self::FILAS)
                ->get(['id', 'titulo', 'numero', 'area', 'estado', 'fecha_emision', 'periodo_fin'])
                ->map(fn (Reporte $r) => [
                    'url' => route('admin.reportes.show', $r->id),
                    'titulo' => $r->titulo,
                    'numero' => $r->numero,
                    'area' => $r->area?->label(),
                    'estado' => $r->estado,
                    'fecha' => ($r->fecha_emision ?? $r->periodo_fin)?->format('d/m/Y'),
                ])->all();
        }

        if ($puede('propuestas')) {
            $ficha['propuestas'] = Propuesta::query()
                ->where('cliente_id', $cliente->id)
                ->orderByDesc('fecha_emision')->orderByDesc('id')
                ->limit(self::FILAS)
                ->get(['id', 'folio', 'titulo', 'estado', 'fecha_emision'])
                ->map(fn (Propuesta $p) => [
                    'url' => route('admin.propuestas.show', $p->id),
                    'titulo' => $p->titulo,
                    'folio' => $p->folio,
                    'estado' => $p->estado,
                    'fecha' => $p->fecha_emision?->format('d/m/Y'),
                ])->all();
        }

        if ($puede('archivos')) {
            $ficha['archivos'] = [
                'contratos' => Archivo::query()
                    ->where('cliente_id', $cliente->id)
                    ->where('tipo', TipoArchivo::Contrato->value)
                    ->orderByDesc('id')
                    ->limit(self::FILAS)
                    ->get(['id', 'nombre', 'extension', 'created_at'])
                    ->map(fn (Archivo $a) => [
                        'url' => route('admin.archivos.download', $a->id),
                        'nombre' => $a->nombre,
                        'extension' => $a->extension,
                        'fecha' => $a->created_at?->format('d/m/Y'),
                    ])->all(),
                'todos_url' => route('admin.archivos.index', ['cliente' => $cliente->id]),
            ];
        }

        return $ficha;
    }

    /**
     * Servicios con su tipo de cobro, el MRR REAL (solo servicios activos de cobro mensual, de FinanzasMetrics, la
     * fuente única) y los ingresos por proyecto aparte: lo de pago único nunca se suma al MRR.
     *
     * @return array<string, mixed>
     */
    private function servicios(Cliente $cliente): array
    {
        $filas = Servicio::query()
            ->where('cliente_id', $cliente->id)
            ->orderByRaw("case estado when 'activo' then 0 when 'pausado' then 1 else 2 end")
            ->orderBy('nombre')
            ->get();

        return [
            'filas' => $filas->map(fn (Servicio $s) => [
                'nombre' => $s->nombre,
                'tipo' => Labels::servicioTipo($s->tipo),
                'cobro' => ($s->tipo_cobro ?? TipoCobro::Mensual)->label(),
                'precio' => $s->etiquetaPrecio(),
                'estado' => $s->estado,
                'aporta_mrr' => $s->aportaMrr(),
                'vencido' => $s->estaVencido(),
                'inicio' => $s->fecha_inicio?->format('d/m/Y'),
                'fin' => $s->fecha_fin?->format('d/m/Y'),
            ])->all(),
            'mrr' => FinanzasMetrics::mrrPorCliente([$cliente->id])->get($cliente->id, 0.0),
            'proyectos' => FinanzasMetrics::ingresosPorProyecto($cliente->id),
        ];
    }

    /**
     * @return list<array{url: string, etiqueta: string}>
     */
    private function integraciones(Cliente $cliente, callable $puede): array
    {
        // Estas tres rutas cuelgan del permiso `clientes`, que la propia ficha ya exige.
        $enlaces = [
            ['url' => route('admin.clientes.integraciones', $cliente), 'etiqueta' => 'Seguimiento y token del snippet'],
            ['url' => route('admin.clientes.conversiones', $cliente), 'etiqueta' => 'Conversiones y embudo'],
            ['url' => route('admin.clientes.clics', $cliente), 'etiqueta' => 'Clics registrados'],
        ];

        if ($puede('integraciones')) {
            $enlaces[] = ['url' => route('admin.integraciones.search-console.index'), 'etiqueta' => 'Search Console'];
        }

        return $enlaces;
    }
}
