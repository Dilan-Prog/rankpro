<?php

namespace App\Enums;

/**
 * Etiquetas de tono de voz que se pueden marcar en la identidad de marca.
 * Cerrada a propósito: son etiquetas de apoyo; el matiz va en el texto libre de «voz».
 */
enum TonoMarca: string
{
    case Cercana = 'cercana';
    case Profesional = 'profesional';
    case Divertida = 'divertida';
    case Elegante = 'elegante';
    case Innovadora = 'innovadora';
    case Confiable = 'confiable';
    case Directa = 'directa';
    case Inspiradora = 'inspiradora';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
