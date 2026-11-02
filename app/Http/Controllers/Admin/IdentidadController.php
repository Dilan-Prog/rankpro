<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FuenteMarca;
use App\Enums\TonoMarca;
use App\Http\Controllers\Controller;
use App\Models\IdentidadMarca;
use App\Support\ClienteActivo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Identidad de marca del cliente activo del menú: una ficha por cliente que se
 * crea al primer guardado. Se guarda con un botón (formulario normal, multipart)
 * y no con autoguardado: el logo es un archivo y guardar a medias mientras se
 * escribe la misión sería más ruido que ayuda.
 */
class IdentidadController extends Controller
{
    public function index(): View
    {
        $cliente = ClienteActivo::modelo();
        $identidad = $cliente?->identidad;

        return view('admin.identidad.index', [
            'pageTitle' => 'Identidad de marca',
            'cliente' => $cliente,
            'identidad' => $identidad,
            'paleta' => old('colores', $identidad?->paleta ?? []),
            'fuentes' => FuenteMarca::cases(),
            'tonos' => TonoMarca::cases(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $cliente = ClienteActivo::modelo();
        if (! $cliente) {
            return redirect()->route('admin.identidad.index')->withErrors(['cliente' => 'Elige un cliente en el selector del menú antes de guardar.']);
        }

        $data = $request->validate([
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'quitar_logo' => ['nullable', 'boolean'],
            'slogan' => ['nullable', 'string', 'max:200'],
            'colores' => ['nullable', 'array', 'max:12'],
            'colores.*.nombre' => ['nullable', 'string', 'max:40'],
            'colores.*.hex' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'fuente_titulos' => ['nullable', Rule::enum(FuenteMarca::class)],
            'fuente_texto' => ['nullable', Rule::enum(FuenteMarca::class)],
            'voz' => ['nullable', 'string', 'max:3000'],
            'tonos' => ['nullable', 'array'],
            'tonos.*' => [Rule::enum(TonoMarca::class)],
            'mision' => ['nullable', 'string', 'max:3000'],
            'vision' => ['nullable', 'string', 'max:3000'],
            'valores' => ['nullable', 'string', 'max:3000'],
        ], [
            'colores.*.hex.required' => 'Cada color necesita un código hexadecimal (por ejemplo #0F9D6E).',
            'colores.*.hex.regex' => 'El código de color debe ser hexadecimal de 6 dígitos, como #0F9D6E.',
            'logo.mimes' => 'El logo debe ser PNG, JPG, WEBP o SVG.',
            'logo.max' => 'El logo no puede pesar más de 2 MB.',
        ]);

        $identidad = $cliente->identidad ?? new IdentidadMarca(['cliente_id' => $cliente->id]);

        $paleta = collect($data['colores'] ?? [])->map(fn (array $c) => [
            'nombre' => trim((string) ($c['nombre'] ?? '')) ?: 'Color',
            'hex' => strtoupper($c['hex']),
        ])->values()->all();

        $rutaAnterior = $identidad->logo_ruta;
        $rutaNueva = $rutaAnterior;

        if ($request->hasFile('logo')) {
            $rutaNueva = $request->file('logo')->store("clientes/{$cliente->id}/identidad", 'local');
        } elseif ($request->boolean('quitar_logo')) {
            $rutaNueva = null;
        }

        $identidad->fill([
            'logo_ruta' => $rutaNueva,
            'slogan' => $data['slogan'] ?? null,
            'paleta' => $paleta,
            'fuente_titulos' => $data['fuente_titulos'] ?? null,
            'fuente_texto' => $data['fuente_texto'] ?? null,
            'voz' => $data['voz'] ?? null,
            'tonos' => array_values(array_unique($data['tonos'] ?? [])),
            'mision' => $data['mision'] ?? null,
            'vision' => $data['vision'] ?? null,
            'valores' => $data['valores'] ?? null,
        ])->save();

        // El logo anterior se borra después de guardar la fila: si el guardado fallara no se pierde el archivo vigente.
        if ($rutaAnterior && $rutaAnterior !== $rutaNueva && Storage::disk('local')->exists($rutaAnterior)) {
            Storage::disk('local')->delete($rutaAnterior);
        }

        return redirect()->route('admin.identidad.index')->with('status', 'Identidad de marca guardada.');
    }

    /** Quita el logo del cliente activo: borra el archivo del disco privado y vacía el campo. */
    public function quitarLogo(): RedirectResponse
    {
        $identidad = ClienteActivo::modelo()?->identidad;

        if (! $identidad?->logo_ruta) {
            return redirect()->route('admin.identidad.index')->withErrors(['logo' => 'Este cliente no tiene un logo que quitar.']);
        }

        $identidad->quitarLogo();

        return redirect()->route('admin.identidad.index')->with('status', 'Logo eliminado.');
    }

    /** Sirve el logo del cliente activo; el disco es privado, por eso pasa por una ruta autenticada. */
    public function logo(): Response
    {
        $ruta = ClienteActivo::modelo()?->identidad?->logo_ruta;
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        // Un SVG abierto directamente podría ejecutar scripts: la política lo deja inerte (dentro de <img> ya lo es).
        return Storage::disk('local')->response($ruta, null, [
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-cache',
        ]);
    }
}
