<?php

namespace App\Support\Api;

/**
 * Configura qué acepta Consulta::aplicar() para un listado dado: en qué
 * columnas busca `search`, qué filtros exactos admite, por qué puede
 * ordenar y qué relaciones puede pedir con `?incluir=`.
 */
final class ConsultaOpciones
{
    /**
     * @param  array<int, string>  $buscarEn
     * @param  array<int, string>  $filtrosExactos
     * @param  array<int, string>  $ordenables
     * @param  array<int, string>  $incluibles
     */
    public function __construct(
        public readonly array $buscarEn = [],
        public readonly array $filtrosExactos = [],
        public readonly array $ordenables = ['id', 'created_at', 'updated_at'],
        public readonly string $ordenPorDefecto = '-created_at',
        public readonly array $incluibles = [],
    ) {
    }
}
