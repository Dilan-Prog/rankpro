<?php

namespace App\Enums;

/**
 * The 4 mandatory stages of the Munch Galindo administrative process a
 * proyecto moves through in order: Planeación -> Organización ->
 * Dirección/Ejecución -> Control/Cierre -> Cerrado.
 */
enum FaseProyecto: string
{
    case Planeacion = 'planeacion';
    case Organizacion = 'organizacion';
    case Direccion = 'direccion';
    case Control = 'control';
    case Cerrado = 'cerrado';

    public function siguiente(): ?self
    {
        return match ($this) {
            self::Planeacion => self::Organizacion,
            self::Organizacion => self::Direccion,
            self::Direccion => self::Control,
            self::Control => self::Cerrado,
            self::Cerrado => null,
        };
    }

    public function anterior(): ?self
    {
        return match ($this) {
            self::Planeacion => null,
            self::Organizacion => self::Planeacion,
            self::Direccion => self::Organizacion,
            self::Control => self::Direccion,
            self::Cerrado => self::Control,
        };
    }

    public function orden(): int
    {
        return match ($this) {
            self::Planeacion => 1,
            self::Organizacion => 2,
            self::Direccion => 3,
            self::Control => 4,
            self::Cerrado => 5,
        };
    }
}
