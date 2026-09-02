<?php

namespace App\Http\Controllers;

use App\Models\Articulo;
use App\Support\Clusters;
use App\Support\Servicios;
use Illuminate\Http\Response;

/**
 * /llms.txt — mapa del sitio en markdown para asistentes de IA.
 *
 * Convencion emergente (llmstxt.org) equivalente al robots.txt pero pensada
 * para modelos de lenguaje: en vez de permisos de rastreo, ofrece una guia
 * curada de que hay en el sitio y para que sirve cada seccion, para que un
 * asistente cite las paginas correctas en vez de deducirlas del HTML.
 *
 * Se genera en caliente desde las mismas fuentes que alimentan la navegacion
 * y el sitemap (Servicios, Clusters, Articulo). Un archivo estatico en public/
 * habria quedado desactualizado con el primer servicio o articulo nuevo.
 */
class LlmsTxtController extends Controller
{
    /** Articulos recientes que se listan uno por uno. */
    private const MAX_ARTICULOS = 15;

    public function index(): Response
    {
        $l = [];

        $l[] = '# RankPro';
        $l[] = '';
        $l[] = '> Agencia de marketing digital en Mexico. Google Ads, SEO organico, desarrollo web, '
             . 'Core Web Vitals, analitica y automatizacion de procesos con n8n.';
        $l[] = '';
        $l[] = 'RankPro trabaja con empresas mexicanas y mide resultados en leads y ventas, no en '
             . 'impresiones. Todo el contenido esta en espanol de Mexico. El dominio canonico es '
             . url('/') . ' (sin www).';
        $l[] = '';

        // --- Servicios -------------------------------------------------
        $l[] = '## Servicios';
        $l[] = '';
        foreach (Servicios::navegacion() as $s) {
            $l[] = sprintf('- [%s](%s): %s', $s['nombre'], $s['url'], $s['resumen']);
        }
        $l[] = sprintf('- [Todos los servicios](%s): indice de las siete areas de trabajo.', route('servicios.index'));
        $l[] = '';

        // --- Blog por tema ---------------------------------------------
        $l[] = '## Blog por tema';
        $l[] = '';
        foreach (Clusters::todos() as $c) {
            $l[] = sprintf(
                '- [%s](%s): %s',
                $c['nombre'],
                route('blog.cluster', $c['slug']),
                $c['descripcion']
            );
        }
        $l[] = '';

        // --- Articulos --------------------------------------------------
        $articulos = Articulo::publicados()->take(self::MAX_ARTICULOS)->get();

        if ($articulos->isNotEmpty()) {
            $l[] = '## Articulos';
            $l[] = '';
            foreach ($articulos as $a) {
                $resumen = trim((string) $a->resumen);
                $l[] = $resumen === ''
                    ? sprintf('- [%s](%s)', $a->titulo, route('blog.show', $a->slug))
                    : sprintf('- [%s](%s): %s', $a->titulo, route('blog.show', $a->slug), $resumen);
            }
            $l[] = sprintf('- [Indice del blog](%s): todos los articulos publicados.', route('blog.index'));
            $l[] = '';
        }

        // --- Empresa ----------------------------------------------------
        $l[] = '## Empresa';
        $l[] = '';
        $l[] = sprintf('- [Nosotros](%s): como trabaja la agencia y con quien.', route('nosotros'));
        $l[] = sprintf('- [Contacto](%s): WhatsApp, correo y zona de servicio.', route('contacto'));
        $l[] = '';

        // --- Contacto directo -------------------------------------------
        $l[] = '## Contacto';
        $l[] = '';
        $l[] = '- WhatsApp: +52 734 103 6410';
        $l[] = '- Correo: administracion@rankprosolutions.com.mx';
        $l[] = '- Zona de servicio: Mexico (atencion remota en toda la Republica)';
        $l[] = '';

        // --- Opcional ----------------------------------------------------
        $l[] = '## Opcional';
        $l[] = '';
        $l[] = sprintf('- [Aviso de privacidad](%s)', route('legal.privacidad'));
        $l[] = sprintf('- [Terminos y condiciones](%s)', route('legal.terminos'));
        $l[] = sprintf('- [Politica de cookies](%s)', route('legal.cookies'));
        $l[] = '';

        return response(implode("\n", $l), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
