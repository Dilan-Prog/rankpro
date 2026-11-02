<?php

namespace App\Enums;

/**
 * Lista curada de familias de Google Fonts para la identidad de marca.
 *
 * Es una lista cerrada (y no texto libre) para que la vista previa siempre
 * pueda cargar la fuente con un único <link> y nadie guarde un nombre que no
 * existe en Google Fonts. El valor es el nombre exacto de la familia.
 */
enum FuenteMarca: string
{
    case DmSans = 'DM Sans';
    case Inter = 'Inter';
    case Poppins = 'Poppins';
    case Montserrat = 'Montserrat';
    case PlayfairDisplay = 'Playfair Display';
    case Lora = 'Lora';
    case Roboto = 'Roboto';
    case OpenSans = 'Open Sans';
    case Raleway = 'Raleway';
    case SpaceGrotesk = 'Space Grotesk';
    case Merriweather = 'Merriweather';
    case Nunito = 'Nunito';

    public function label(): string
    {
        return $this->value;
    }

    /** Hoja de estilos de Google Fonts de esta familia (400, 600 y 700). */
    public function urlGoogleFonts(): string
    {
        return 'https://fonts.googleapis.com/css2?family='.str_replace(' ', '+', $this->value).':wght@400;600;700&display=swap';
    }
}
