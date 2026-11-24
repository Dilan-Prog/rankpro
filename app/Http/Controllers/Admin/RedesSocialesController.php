<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RedSocial;
use App\Support\CatalogoRedes;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Redes sociales del pie. Es una lista corta que se guarda entera: el orden en que
 * llegan las filas es el orden en que se pintan.
 */
class RedesSocialesController extends Controller
{
    private const MAXIMO = 15;

    public function edit(): View
    {
        return view('admin.configuracion.redes', [
            'pageTitle' => 'Redes sociales',
            'redes' => RedSocial::query()->orderBy('orden')->orderBy('id')->get(),
            'catalogo' => CatalogoRedes::todas(),
            'maximo' => self::MAXIMO,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        // Una fila sin enlace es una fila que no se llegó a usar: se ignora.
        $filas = collect((array) $request->input('redes', []))
            ->filter(fn ($f) => is_array($f) && is_string($f['url'] ?? null) && trim($f['url']) !== '')
            ->map(fn (array $f): array => [
                'red' => $f['red'] ?? null,
                'nombre' => is_string($f['nombre'] ?? null) && trim($f['nombre']) !== '' ? trim($f['nombre']) : null,
                'url' => $this->normalizarUrl($f['url']),
                'activa' => ! empty($f['activa']),
            ])->all();

        $validador = Validator::make(['redes' => $filas], [
            'redes' => ['array', 'max:'.self::MAXIMO],
            'redes.*.red' => ['required', 'string', Rule::in(array_keys(CatalogoRedes::todas()))],
            'redes.*.nombre' => ['nullable', 'string', 'max:60', 'required_if:redes.*.red,enlace'],
            'redes.*.url' => ['required', 'string', 'max:255', 'url:http,https'],
        ], [
            'redes.max' => 'Puedes agregar hasta '.self::MAXIMO.' enlaces.',
            'redes.*.red.required' => 'Elige un icono.',
            'redes.*.red.in' => 'Elige un icono de la lista.',
            'redes.*.nombre.required_if' => 'Con el icono «Otro enlace» escribe un nombre, para que se sepa a dónde lleva.',
            'redes.*.nombre.max' => 'El nombre no puede pasar de 60 caracteres.',
            'redes.*.url.url' => 'Escribe un enlace completo, por ejemplo https://www.facebook.com/tu-pagina.',
            'redes.*.url.max' => 'El enlace no puede pasar de 255 caracteres.',
        ]);

        // Evita el error típico: elegir Instagram y pegar el enlace de Facebook.
        $validador->after(function ($v) use ($filas): void {
            foreach ($filas as $i => $f) {
                if ($v->errors()->has("redes.$i.*") || ! is_string($f['red']) || CatalogoRedes::admiteUrl($f['red'], $f['url'])) {
                    continue;
                }

                $nombre = CatalogoRedes::todas()[$f['red']]['nombre'] ?? 'esa red';
                $v->errors()->add("redes.$i.url", "Este enlace no parece de {$nombre}. Revisa el icono o el enlace.");
            }
        });

        $validador->validate();

        DB::transaction(function () use ($filas): void {
            RedSocial::query()->delete();

            foreach (array_values($filas) as $orden => $fila) {
                RedSocial::query()->create($fila + ['orden' => $orden]);
            }
        });

        // El delete masivo no dispara eventos y la caché pudo rellenarse a media transacción.
        Cache::forget(RedSocial::CACHE_CLAVE);

        return redirect()->route('admin.configuracion.redes.edit')
            ->with('status', 'Guardado. El pie del sitio ya muestra los enlaces activos.');
    }

    /** «facebook.com/rankpro» => «https://facebook.com/rankpro»; respeta cualquier esquema ya escrito. */
    private function normalizarUrl(string $url): string
    {
        $url = trim($url);

        return preg_match('#^[a-z][a-z0-9+.-]*:#i', $url) ? $url : 'https://'.ltrim($url, '/');
    }
}
