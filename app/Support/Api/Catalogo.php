<?php

namespace App\Support\Api;

use App\Support\Webhooks\Eventos;

/**
 * GET /api/v1/catalogo: enums + labels legibles, para que n8n pueda mostrar
 * selects sin tener que adivinar los valores válidos. Usa el label() del
 * propio enum cuando existe; si no, humaniza el value (los enums viejos del
 * proyecto casi ninguno tiene label() — ver App\Support\Labels, que traduce
 * por fuera en las vistas en vez de en el enum).
 */
class Catalogo
{
    /** @return array<string, array<int, array{valor: string, etiqueta: string}>> */
    public static function enums(): array
    {
        $salida = [];
        foreach (glob(app_path('Enums/*.php')) as $archivo) {
            $clase = 'App\\Enums\\'.basename($archivo, '.php');
            if (! enum_exists($clase)) {
                continue;
            }
            $clave = self::aSnake(basename($archivo, '.php'));
            $salida[$clave] = array_map(function ($caso) {
                $etiqueta = method_exists($caso, 'label')
                    ? $caso->label()
                    : ucfirst(str_replace('_', ' ', $caso->value));

                return ['valor' => $caso->value, 'etiqueta' => $etiqueta];
            }, $clase::cases());
        }

        return $salida;
    }

    private static function aSnake(string $pascal): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $pascal));
    }

    /** @return array<string, mixed> */
    public static function todo(): array
    {
        return [
            'enums' => self::enums(),
            'modulos' => Modulos::TODOS,
            'eventos_webhook' => Eventos::lista(),
        ];
    }
}
