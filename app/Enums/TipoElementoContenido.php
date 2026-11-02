<?php

namespace App\Enums;

/** Tipos de elemento del árbol del módulo «Contenido» (tabla cm_items). */
enum TipoElementoContenido: string
{
    case Carpeta = 'carpeta';
    case Documento = 'documento';
    case Tabla = 'tabla';
    case Archivo = 'archivo';
    case Pizarra = 'pizarra';

    public function label(): string
    {
        return match ($this) {
            self::Carpeta => 'Carpeta',
            self::Documento => 'Documento',
            self::Tabla => 'Tabla',
            self::Archivo => 'Archivo',
            self::Pizarra => 'Pizarra',
        };
    }

    /** Clase de FontAwesome para listas y menús. */
    public function icono(): string
    {
        return match ($this) {
            self::Carpeta => 'fa-folder',
            self::Documento => 'fa-file-lines',
            self::Tabla => 'fa-table',
            self::Archivo => 'fa-file',
            self::Pizarra => 'fa-chalkboard',
        };
    }
}
