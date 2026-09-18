<?php

namespace App\Support\Correo;

use App\Models\Cliente;

/**
 * Variables que admite una plantilla de correo, con su origen.
 *
 * Fuente única: el editor las ofrece como chips, el redactor de envíos las pide
 * como campos, y el renderizador las sustituye. Las de ámbito `persona` salen
 * del cliente destinatario (o del correo suelto); las de ámbito `envio` las
 * escribe quien redacta y valen para todos los destinatarios.
 */
class Variables
{
    /**
     * @return array<string, array{label: string, ambito: 'persona'|'envio', ejemplo: string}>
     */
    public static function catalogo(): array
    {
        return [
            'cliente' => ['label' => 'Empresa del cliente', 'ambito' => 'persona', 'ejemplo' => 'Hotel Fratelli'],
            'contacto' => ['label' => 'Nombre del contacto', 'ambito' => 'persona', 'ejemplo' => 'María'],
            'servicio' => ['label' => 'Servicio', 'ambito' => 'envio', 'ejemplo' => 'SEO orgánico'],
            'mes' => ['label' => 'Mes del reporte', 'ambito' => 'envio', 'ejemplo' => 'agosto 2026'],
            'monto' => ['label' => 'Monto', 'ambito' => 'envio', 'ejemplo' => '$3,000 MXN'],
            'enlace_reporte' => ['label' => 'Enlace al reporte', 'ambito' => 'envio', 'ejemplo' => 'https://…'],
            'responsable' => ['label' => 'Responsable en RankPro', 'ambito' => 'envio', 'ejemplo' => 'Dilan García'],
        ];
    }

    /** @return array<int, string> */
    public static function claves(): array
    {
        return array_keys(self::catalogo());
    }

    /**
     * Variables de ámbito persona resueltas desde un cliente del CRM.
     *
     * @return array<string, string>
     */
    public static function desdeCliente(Cliente $cliente): array
    {
        return [
            'cliente' => (string) ($cliente->empresa ?: $cliente->nombre),
            'contacto' => (string) ($cliente->contacto_nombre ?: $cliente->nombre),
        ];
    }

    /**
     * Sustituye `{{clave}}` por su valor. Una variable sin valor se deja vacía,
     * nunca se imprime el marcador: un "{{contacto}}" en un correo real es peor
     * que un hueco.
     *
     * @param  array<string, mixed>  $valores
     */
    public static function sustituir(string $texto, array $valores): string
    {
        return (string) preg_replace_callback(
            '/\{\{\s*([a-z_]+)\s*\}\}/i',
            fn ($m) => (string) ($valores[strtolower($m[1])] ?? ''),
            $texto
        );
    }

    /**
     * Claves `{{...}}` presentes en un texto (o en cualquier estructura de
     * bloques serializada), para avisar de cuáles faltan antes de enviar.
     *
     * @return array<int, string>
     */
    public static function usadasEn(string $texto): array
    {
        preg_match_all('/\{\{\s*([a-z_]+)\s*\}\}/i', $texto, $m);

        return array_values(array_unique(array_map('strtolower', $m[1])));
    }
}
