<?php

namespace App\Http\Controllers\Portal;

use App\Support\Portal\Marca;
use App\Support\Portal\Respuesta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Logo del cliente del enlace (disco privado); es el único archivo de identidad que sale por el portal. */
class LogoController extends PortalController
{
    public function __invoke(Request $request): StreamedResponse
    {
        $ruta = Marca::rutaLogo($this->cliente($request));
        if ($ruta === null) {
            Respuesta::abortar(404);
        }

        // Un SVG abierto directamente podría ejecutar scripts: la política lo deja inerte (dentro de <img> ya lo es).
        return Storage::disk('local')->response($ruta, null, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
        ]);
    }
}
