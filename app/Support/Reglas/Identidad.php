<?php

namespace App\Support\Reglas;

use App\Enums\FuenteMarca;
use App\Enums\TonoMarca;
use Illuminate\Validation\Rule;

/**
 * Reglas de la ficha de identidad de marca, compartidas entre IdentidadController (cliente activo) y el
 * asistente de alta de cliente, para que las dos puertas validen y normalicen igual.
 */
class Identidad
{
    public static function guardar(): array
    {
        return [
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'quitar_logo' => ['nullable', 'boolean'],
            'slogan' => ['nullable', 'string', 'max:200'],
            'colores' => ['nullable', 'array', 'max:12'],
            'colores.*.nombre' => ['nullable', 'string', 'max:40'],
            'colores.*.hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fuente_titulos' => ['nullable', Rule::enum(FuenteMarca::class)],
            'fuente_texto' => ['nullable', Rule::enum(FuenteMarca::class)],
            'voz' => ['nullable', 'string', 'max:3000'],
            'tonos' => ['nullable', 'array'],
            'tonos.*' => [Rule::enum(TonoMarca::class)],
            'mision' => ['nullable', 'string', 'max:3000'],
            'vision' => ['nullable', 'string', 'max:3000'],
            'valores' => ['nullable', 'string', 'max:3000'],
        ];
    }

    /** @return array<string, string> */
    public static function mensajes(): array
    {
        return [
            'colores.*.hex.required' => 'Cada color necesita un código hexadecimal (por ejemplo #0F9D6E).',
            'colores.*.hex.regex' => 'El código de color debe ser hexadecimal de 6 dígitos, como #0F9D6E.',
            'logo.mimes' => 'El logo debe ser PNG, JPG, WEBP o SVG.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
        ];
    }

    /**
     * Paleta como se guarda: nombre con respaldo "Color" y hex en mayúsculas.
     *
     * @param  array<int, array{nombre?: ?string, hex: string}>  $colores
     * @return array<int, array{nombre: string, hex: string}>
     */
    public static function paleta(array $colores): array
    {
        return collect($colores)->map(fn (array $c) => [
            'nombre' => trim((string) ($c['nombre'] ?? '')) ?: 'Color',
            'hex' => strtoupper($c['hex']),
        ])->values()->all();
    }
}
