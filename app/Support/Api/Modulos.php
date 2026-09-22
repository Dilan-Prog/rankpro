<?php

namespace App\Support\Api;

/**
 * Catálogo de módulos que pueden protegerse con una habilidad de token
 * ('{modulo}:leer' / '{modulo}:escribir' / '*'). Misma lista que
 * PermissionSeeder, para que roles del panel y habilidades de la API
 * hablen de los mismos módulos.
 */
class Modulos
{
    public const TODOS = [
        'dashboard', 'clientes', 'servicios', 'seo', 'keywords', 'blog', 'ads',
        'automatizaciones', 'conversiones', 'reportes', 'propuestas', 'correo',
        'desarrollo', 'finanzas', 'archivos', 'usuarios', 'roles', 'integraciones',
        'webhooks',
    ];

    /** @return array<int, string> */
    public static function habilidades(): array
    {
        $habilidades = ['*'];
        foreach (self::TODOS as $modulo) {
            $habilidades[] = "{$modulo}:leer";
            $habilidades[] = "{$modulo}:escribir";
        }

        return $habilidades;
    }

    public static function etiqueta(string $modulo): string
    {
        $etiquetas = [
            'dashboard' => 'Dashboard', 'clientes' => 'CRM Clientes', 'servicios' => 'Servicios',
            'seo' => 'Módulo SEO', 'keywords' => 'Keywords', 'blog' => 'Blog', 'ads' => 'Módulo Ads',
            'automatizaciones' => 'Automatizaciones', 'conversiones' => 'Conversiones',
            'reportes' => 'Reportes', 'propuestas' => 'Propuestas', 'correo' => 'Correo',
            'desarrollo' => 'Desarrollo', 'finanzas' => 'Finanzas', 'archivos' => 'Archivos',
            'usuarios' => 'Usuarios', 'roles' => 'Roles', 'integraciones' => 'Integraciones',
            'webhooks' => 'Webhooks',
        ];

        return $etiquetas[$modulo] ?? ucfirst($modulo);
    }

    /**
     * Si viene '{x}:escribir' junto con '{x}:leer', el leer es redundante
     * (escribir ya lo implica) y se quita para que la UI de tokens no
     * muestre dos chips que dicen lo mismo. Si viene '*' se queda solo eso.
     *
     * @param  array<int, string>  $habilidades
     * @return array<int, string>
     */
    public static function normalizar(array $habilidades): array
    {
        $habilidades = array_values(array_unique($habilidades));

        if (in_array('*', $habilidades, true)) {
            return ['*'];
        }

        return array_values(array_filter($habilidades, function ($h) use ($habilidades) {
            if (! str_ends_with($h, ':leer')) {
                return true;
            }
            $modulo = substr($h, 0, -5);

            return ! in_array("{$modulo}:escribir", $habilidades, true);
        }));
    }
}
