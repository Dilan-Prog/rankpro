<?php

namespace App\Support\Tablero;

use App\Models\Cliente;
use App\Models\User;
use Carbon\CarbonInterface;

/**
 * Lo que comparten los calculadores de bloque durante UNA petición: el cliente, «hoy», los permisos del
 * usuario y las consultas que más de un bloque necesita (se hacen una vez y se memorizan, para no repetirlas
 * por bloque ni caer en N+1).
 */
class Contexto
{
    /** @var array<string, mixed> */
    private array $memo = [];

    public function __construct(
        public readonly Cliente $cliente,
        public readonly CarbonInterface $hoy,
        public readonly ?User $usuario = null,
    ) {
    }

    /** Sin usuario (cálculo interno o pruebas) se ve todo, igual que ResumenAgencia. */
    public function puede(string $modulo): bool
    {
        return $this->usuario === null || $this->usuario->tienePermiso($modulo);
    }

    /** @param  list<string>  $modulos  Basta uno. */
    public function puedeAlguno(array $modulos): bool
    {
        foreach ($modulos as $m) {
            if ($this->puede($m)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cálculo del Tablero por cliente (keywords y tareas); lo usan Keywords, Salud y Contenido/Desarrollo.
     *
     * @return array<string, mixed>
     */
    public function tablero(): array
    {
        return $this->memo['tablero'] ??= (new TableroCliente($this->hoy))->calcular($this->cliente);
    }

    /** Memoriza el resultado de una consulta compartida bajo una clave. */
    public function recordar(string $clave, callable $consulta): mixed
    {
        if (! array_key_exists($clave, $this->memo)) {
            $this->memo[$clave] = $consulta();
        }

        return $this->memo[$clave];
    }
}
