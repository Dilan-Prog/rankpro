<?php

namespace App\Http\Controllers;

use App\Support\Servicios;
use Illuminate\View\View;

/**
 * Páginas públicas del sitio de RankPro.
 *
 * Toda la arquitectura de contenido (servicios, nosotros, contacto y legales)
 * se sirve desde aquí. Los datos de cada servicio viven en servicios() para que
 * el hub (/servicios) y el detalle (/servicios/{slug}) usen exactamente la misma
 * fuente de verdad y no se desincronicen los textos ni los iconos.
 */
class PaginasController extends Controller
{
    /**
     * Catálogo de servicios. La clave del array es el slug de la URL.
     *
     * La fuente de verdad vive en App\Support\Servicios para que el navbar y el
     * footer usen exactamente los mismos nombres, iconos y resumenes.
     *
     * @return array<string, array<string, mixed>>
     */
    private function servicios(): array
    {
        return Servicios::todos();
    }

    public function nosotros(): View
    {
        return view('pages.nosotros', [
            'servicios' => $this->servicios(),
        ]);
    }

    public function serviciosIndex(): View
    {
        return view('pages.servicios.index', [
            'servicios' => $this->servicios(),
        ]);
    }

    public function serviciosShow(string $slug): View
    {
        $servicios = $this->servicios();

        abort_unless(array_key_exists($slug, $servicios), 404);

        // Algunos servicios tienen una landing propia orientada a conversion
        // (pages/servicios/{slug}.blade.php). El resto usa la plantilla generica.
        $vista = view()->exists("pages.servicios.{$slug}")
            ? "pages.servicios.{$slug}"
            : 'pages.servicios.show';

        return view($vista, [
            'servicio' => $servicios[$slug],
            'otrosServicios' => array_values(array_diff_key($servicios, [$slug => true])),
        ]);
    }

    public function contacto(): View
    {
        return view('pages.contacto', [
            'servicios' => $this->servicios(),
        ]);
    }

    public function terminos(): View
    {
        return view('pages.legal.terminos');
    }

    public function privacidad(): View
    {
        return view('pages.legal.privacidad');
    }

    public function cookies(): View
    {
        return view('pages.legal.cookies');
    }
}
