<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Agrupa los permisos (uno por módulo del panel) con etiquetas legibles para
 * la matriz de /admin/roles y el formulario de rol.
 *
 * La lista de grupos es solo presentación: los permisos salen siempre de la
 * tabla `permissions`. Un módulo que aún no tenga grupo aquí cae en "Otros" en
 * vez de desaparecer de la matriz, que es justo lo que se quiere evitar al
 * sumar módulos nuevos.
 */
class GruposPermisos
{
    /** @var array<string, list<string>> título => módulos (en el orden en que se muestran) */
    public const GRUPOS = [
        'Panel y trabajo diario' => ['inicio', 'dashboard', 'mi-espacio', 'calendario', 'agenda'],
        'Clientes y ventas' => ['clientes', 'servicios', 'propuestas', 'conversiones', 'finanzas'],
        'Marca y contenido' => ['identidad', 'audiencia', 'contenido', 'blog'],
        'SEO, publicidad y resultados' => ['seo-plan', 'seo', 'keywords', 'ads', 'metricas', 'reportes'],
        'Operación' => ['automatizaciones', 'desarrollo', 'captura', 'archivos'],
        'Correo' => ['correo', 'configuracion'],
        'Administración' => ['usuarios', 'roles', 'integraciones', 'webhooks'],
    ];

    public const OTROS = 'Otros';

    public static function tituloDe(string $modulo): string
    {
        foreach (self::GRUPOS as $titulo => $modulos) {
            if (in_array($modulo, $modulos, true)) {
                return $titulo;
            }
        }

        return self::OTROS;
    }

    /**
     * Ordena los permisos por grupo y, dentro del grupo, por el orden definido.
     *
     * @param  Collection<int, \App\Models\Permission>  $permisos
     */
    public static function ordenar(Collection $permisos): Collection
    {
        return collect(self::agrupar($permisos))->flatMap(fn (array $g) => $g['permisos'])->values();
    }

    /**
     * @param  Collection<int, \App\Models\Permission>  $permisos
     * @return list<array{titulo: string, permisos: list<\App\Models\Permission>}>
     */
    public static function agrupar(Collection $permisos): array
    {
        $titulos = array_merge(array_keys(self::GRUPOS), [self::OTROS]);
        $porGrupo = array_fill_keys($titulos, []);

        foreach ($permisos as $permiso) {
            $porGrupo[self::tituloDe($permiso->module)][] = $permiso;
        }

        $resultado = [];
        foreach ($porGrupo as $titulo => $lista) {
            if ($lista === []) {
                continue;
            }
            $orden = self::GRUPOS[$titulo] ?? [];
            usort($lista, function ($a, $b) use ($orden) {
                $pa = array_search($a->module, $orden, true);
                $pb = array_search($b->module, $orden, true);

                return [$pa === false ? PHP_INT_MAX : $pa, $a->label] <=> [$pb === false ? PHP_INT_MAX : $pb, $b->label];
            });
            $resultado[] = ['titulo' => $titulo, 'permisos' => $lista];
        }

        return $resultado;
    }
}
