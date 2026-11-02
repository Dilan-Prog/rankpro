<?php

namespace App\Support\Contenido;

use App\Models\AdsCampana;
use App\Models\Finanza;
use App\Models\Keyword;
use App\Models\Proyecto;
use App\Models\Tarea;
use App\Models\User;

/**
 * Fuentes reales de SOLO LECTURA con las que una tabla de «Contenido» puede
 * «llenarse» (menú «Llenar desde...»).
 *
 * Es una foto: se copian los datos de ese momento a columnas tipadas y la
 * tabla deja de depender de la fuente (no hay sincronización en vivo, y la UI
 * lo dice con la fecha). Dos reglas de seguridad:
 *  - cada fuente exige el permiso del módulo de donde salen los datos, así que
 *    la tabla no sirve para saltarse el control por módulo;
 *  - todo se filtra por el cliente de la tabla, nunca por lo que mande el navegador.
 */
class FuentesTabla
{
    /** clave => [etiqueta, permiso del módulo, descripción de las columnas] */
    private const FUENTES = [
        'ads_campanas' => ['Campañas de Ads', 'ads', 'Campaña, plataforma, estado y presupuesto mensual'],
        'desarrollo_tareas' => ['Tareas de Desarrollo', 'desarrollo', 'Título, proyecto, estado y fecha límite'],
        'desarrollo_proyectos' => ['Proyectos de Desarrollo', 'desarrollo', 'Título, estado y fecha de entrega estimada'],
        'finanzas' => ['Facturas y finanzas', 'finanzas', 'Concepto, monto, estado y vencimiento'],
        'keywords' => ['Keywords', 'keywords', 'Palabra y posición actual'],
    ];

    private const PLATAFORMAS = ['google_ads' => 'Google Ads', 'meta_ads' => 'Meta Ads', 'tiktok_ads' => 'TikTok Ads'];
    private const ESTADOS_CAMPANA = ['activa' => 'Activa', 'pausada' => 'Pausada', 'finalizada' => 'Finalizada'];
    private const ESTADOS_TAREA = ['pendiente' => 'Pendiente', 'en_progreso' => 'En progreso', 'en_revision' => 'En revisión', 'completada' => 'Completada'];
    private const ESTADOS_PROYECTO = ['activo' => 'Activo', 'pausado' => 'Pausado', 'cancelado' => 'Cancelado', 'cerrado' => 'Cerrado'];
    private const ESTADOS_FINANZA = ['pagado' => 'Pagado', 'pendiente' => 'Pendiente', 'vencido' => 'Vencido'];

    public static function claves(): array
    {
        return array_keys(self::FUENTES);
    }

    /** @return array<string, string> clave => etiqueta de TODAS las fuentes (para rotular una foto aunque ya no se pueda repetir). */
    public static function etiquetas(): array
    {
        return array_map(fn ($f) => $f[0], self::FUENTES);
    }

    public static function etiqueta(string $clave): ?string
    {
        return self::FUENTES[$clave][0] ?? null;
    }

    /**
     * Fuentes que este usuario puede usar: solo las de módulos a los que tiene acceso.
     *
     * @return list<array{clave: string, etiqueta: string, descripcion: string}>
     */
    public static function disponibles(User $usuario): array
    {
        $salida = [];

        foreach (self::FUENTES as $clave => [$etiqueta, $permiso, $descripcion]) {
            if ($usuario->tienePermiso($permiso)) {
                $salida[] = ['clave' => $clave, 'etiqueta' => $etiqueta, 'descripcion' => $descripcion];
            }
        }

        return $salida;
    }

    public static function permite(User $usuario, string $clave): bool
    {
        return isset(self::FUENTES[$clave]) && $usuario->tienePermiso(self::FUENTES[$clave][1]);
    }

    /**
     * Foto de la fuente para un cliente.
     *
     * @return array{columnas: list<array>, filas: list<list<string>>, total: int}
     */
    public static function foto(string $clave, int $clienteId): array
    {
        return match ($clave) {
            'ads_campanas' => self::campanas($clienteId),
            'desarrollo_tareas' => self::tareas($clienteId),
            'desarrollo_proyectos' => self::proyectos($clienteId),
            'finanzas' => self::finanzas($clienteId),
            'keywords' => self::keywords($clienteId),
        };
    }

    private static function campanas(int $clienteId): array
    {
        $consulta = AdsCampana::query()->where('cliente_id', $clienteId)->orderBy('nombre')->orderBy('id');

        return self::armar([
            self::col('Campaña', 'texto'),
            self::col('Plataforma', 'seleccion', array_values(self::PLATAFORMAS)),
            self::col('Estado', 'seleccion', array_values(self::ESTADOS_CAMPANA)),
            self::col('Presupuesto mensual', 'numero'),
        ], $consulta, fn (AdsCampana $c) => [
            $c->nombre,
            self::PLATAFORMAS[$c->plataforma] ?? (string) $c->plataforma,
            self::ESTADOS_CAMPANA[$c->estado?->value] ?? '',
            $c->presupuesto_mensual,
        ]);
    }

    private static function tareas(int $clienteId): array
    {
        // Solo tareas colgadas de un proyecto del cliente: las personales de «Mi espacio» no tienen proyecto.
        $consulta = Tarea::query()
            ->with('proyecto:id,nombre')
            ->whereHas('proyecto', fn ($q) => $q->where('cliente_id', $clienteId))
            ->orderByRaw('fecha_limite is null')
            ->orderBy('fecha_limite')
            ->orderBy('id');

        return self::armar([
            self::col('Título', 'texto'),
            self::col('Proyecto', 'texto'),
            self::col('Estado', 'seleccion', array_values(self::ESTADOS_TAREA)),
            self::col('Fecha límite', 'fecha'),
        ], $consulta, fn (Tarea $t) => [
            $t->titulo,
            $t->proyecto?->nombre,
            self::ESTADOS_TAREA[$t->estado?->value] ?? '',
            $t->fecha_limite?->format('Y-m-d'),
        ]);
    }

    private static function proyectos(int $clienteId): array
    {
        $consulta = Proyecto::query()
            ->where('cliente_id', $clienteId)
            ->orderByRaw('fecha_entrega_estimada is null')
            ->orderBy('fecha_entrega_estimada')
            ->orderBy('id');

        return self::armar([
            self::col('Título', 'texto'),
            self::col('Estado', 'seleccion', array_values(self::ESTADOS_PROYECTO)),
            self::col('Fecha límite', 'fecha'),
        ], $consulta, fn (Proyecto $p) => [
            $p->nombre,
            self::ESTADOS_PROYECTO[$p->estado?->value] ?? '',
            $p->fecha_entrega_estimada?->format('Y-m-d'),
        ]);
    }

    private static function finanzas(int $clienteId): array
    {
        $consulta = Finanza::query()
            ->where('cliente_id', $clienteId)
            ->orderByRaw('fecha_vencimiento is null')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id');

        return self::armar([
            self::col('Concepto', 'texto'),
            self::col('Monto', 'numero'),
            self::col('Estado', 'seleccion', array_values(self::ESTADOS_FINANZA)),
            self::col('Vencimiento', 'fecha'),
        ], $consulta, fn (Finanza $f) => [
            $f->concepto,
            $f->monto,
            self::ESTADOS_FINANZA[$f->estado?->value] ?? '',
            $f->fecha_vencimiento?->format('Y-m-d'),
        ]);
    }

    private static function keywords(int $clienteId): array
    {
        $consulta = Keyword::query()->where('cliente_id', $clienteId)->orderBy('keyword')->orderBy('id');

        return self::armar([
            self::col('Palabra', 'texto'),
            self::col('Posición actual', 'numero'),
        ], $consulta, fn (Keyword $k) => [$k->keyword, $k->posicion_actual]);
    }

    private static function col(string $nombre, string $tipo, array $opciones = []): array
    {
        return ['nombre' => $nombre, 'tipo' => $tipo, 'opciones' => $opciones];
    }

    /** Ejecuta la consulta con el tope de filas de la tabla y vuelve cada registro una lista de textos. */
    private static function armar(array $columnas, $consulta, callable $fila): array
    {
        $total = (clone $consulta)->count();
        $filas = [];

        foreach ($consulta->limit(TablaContenido::MAX_FILAS)->get() as $registro) {
            $filas[] = array_map(fn ($v) => $v === null ? '' : (string) $v, $fila($registro));
        }

        return ['columnas' => $columnas, 'filas' => $filas, 'total' => $total];
    }
}
