<?php

namespace App\Support\Tablero;

use App\Models\Cliente;
use App\Models\User;
use App\Support\Tablero\Bloques\Ads;
use App\Support\Tablero\Bloques\Alertas;
use App\Support\Tablero\Bloques\Audiencias;
use App\Support\Tablero\Bloques\Bloque;
use App\Support\Tablero\Bloques\Contenido;
use App\Support\Tablero\Bloques\Conversiones;
use App\Support\Tablero\Bloques\Keywords;
use App\Support\Tablero\Bloques\Metricas;
use App\Support\Tablero\Bloques\Salud;
use App\Support\Tablero\Bloques\Seo;
use Carbon\CarbonInterface;

/**
 * «Resumen de cliente»: tablero con un semáforo por bloque (salud, SEO, keywords, Google Ads, Meta Ads,
 * conversiones, métricas, audiencia, contenido y alertas). Solo orquesta: cada bloque vive en `Bloques\*`
 * con el contrato `{estado_semaforo, cifras:[{etiqueta, valor, fecha, fuente}], enlace}`.
 *
 * Un bloque que el usuario no puede ver NO se calcula (ni se consulta) y sale en `ocultos`: no se muestra
 * ni como hueco, porque «sin dato» y «no tienes acceso» no son lo mismo. El Tablero de Reportes
 * (TableroCliente) no se toca: convive y este resumen reutiliza su `analizar()` para Keywords.
 */
class ResumenCliente
{
    public function __construct(private readonly ?CarbonInterface $hoy = null)
    {
    }

    /**
     * Orden en pantalla: primero lo que dice si la cuenta está sana.
     *
     * @return list<Bloque>
     */
    public static function bloques(): array
    {
        return [
            new Salud(),
            new Alertas(),
            new Seo(),
            new Keywords(),
            new Ads('google_ads'),
            new Ads('meta_ads'),
            new Conversiones(),
            new Metricas(),
            new Audiencias(),
            new Contenido(),
        ];
    }

    /**
     * @return array{cliente: array{id: int, nombre: string}, hoy: string, bloques: array<string, array<string, mixed>>, ocultos: list<string>, conteo: array<string, int>}
     */
    public function calcular(Cliente $cliente, ?User $usuario = null): array
    {
        $hoy = $this->hoy ?? now();
        $ctx = new Contexto($cliente, $hoy, $usuario);

        $bloques = [];
        $ocultos = [];
        $conteo = [Bloque::VERDE => 0, Bloque::AMARILLO => 0, Bloque::ROJO => 0, Bloque::NEUTRO => 0];

        foreach (self::bloques() as $bloque) {
            if (! $ctx->puedeAlguno($bloque->permisos())) {
                $ocultos[] = $bloque->clave();

                continue;
            }

            $resultado = $bloque->calcular($ctx);
            $bloques[$bloque->clave()] = $resultado;
            $conteo[$resultado['estado_semaforo']]++;
        }

        return [
            'cliente' => ['id' => $cliente->id, 'nombre' => $cliente->nombre_comercial],
            'hoy' => $hoy->format('d/m/Y'),
            'bloques' => $bloques,
            'ocultos' => $ocultos,
            'conteo' => $conteo,
        ];
    }
}
