<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConfiguracionMedicion;
use App\Support\ExtractorEtiquetasGoogle;
use App\Support\FormatosGoogle;
use App\Support\Medicion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Medición y Google: el dueño pega el código que da Google y el sitio público carga
 * GTM / GA4 / Google Ads sin tocar el .env (ver App\Support\Medicion).
 *
 * `codigo_pegado` es solo una comodidad: se pasa por el extractor, se quedan los IDs
 * que cumplen los formatos y el texto se descarta (no se guarda ni vuelve a la vista,
 * tampoco en el old() de una validación fallida).
 */
class ConfiguracionMedicionController extends Controller
{
    /** Campo del formulario => columna de la etiqueta de conversión que rellena `conversion_destino`. */
    private const DESTINOS_CONVERSION = [
        'agendar' => 'conversion_agendar',
        'whatsapp' => 'conversion_whatsapp',
        'telefono' => 'conversion_telefono',
    ];

    public function edit(): View
    {
        return view('admin.configuracion.medicion', [
            'pageTitle' => 'Medición y Google',
            'config' => ConfiguracionMedicion::actual(),
            'estado' => Medicion::estado(),
            'hostActual' => Medicion::hostActual(),
            'formatos' => FormatosGoogle::ejemplos(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $detectado = $this->absorberCodigoPegado($request);

        // Normaliza antes de validar: así «gtm-abc1234 » es válido y se guarda en mayúsculas.
        $request->merge([
            'gtm_id' => FormatosGoogle::normalizarId($request->input('gtm_id')),
            'ga4_id' => FormatosGoogle::normalizarId($request->input('ga4_id')),
            'google_ads_id' => FormatosGoogle::normalizarId($request->input('google_ads_id')),
            'conversion_agendar' => FormatosGoogle::normalizarConversion($request->input('conversion_agendar')),
            'conversion_whatsapp' => FormatosGoogle::normalizarConversion($request->input('conversion_whatsapp')),
            'conversion_telefono' => FormatosGoogle::normalizarConversion($request->input('conversion_telefono')),
        ]);

        $data = $request->validate([
            'gtm_id' => ['nullable', 'string', 'regex:'.FormatosGoogle::GTM],
            'ga4_id' => ['nullable', 'string', 'regex:'.FormatosGoogle::GA4],
            'google_ads_id' => ['nullable', 'string', 'regex:'.FormatosGoogle::ADS],
            'conversion_agendar' => ['nullable', 'string', 'regex:'.FormatosGoogle::CONVERSION],
            'conversion_whatsapp' => ['nullable', 'string', 'regex:'.FormatosGoogle::CONVERSION],
            'conversion_telefono' => ['nullable', 'string', 'regex:'.FormatosGoogle::CONVERSION],
            'dominios' => ['nullable', 'string', 'max:255'],
            'activa' => ['nullable', 'boolean'],
        ], $this->mensajes());

        $data['activa'] = $request->boolean('activa');
        $data['dominios'] = $this->normalizarDominios($data['dominios'] ?? null);

        // Activar sin ningún ID dejaría el interruptor "encendido" sin emitir nada, y el
        // dueño creería que mide: se exige al menos uno.
        if ($data['activa'] && ! ($data['gtm_id'] ?? null) && ! ($data['ga4_id'] ?? null) && ! ($data['google_ads_id'] ?? null)) {
            throw ValidationException::withMessages([
                'activa' => 'Para activar la medición pega o escribe al menos un ID válido (Tag Manager, GA4 o Google Ads).',
            ]);
        }

        $config = ConfiguracionMedicion::query()->find(1) ?? new ConfiguracionMedicion();
        $config->forceFill($data + ['id' => 1, 'actualizado_por' => Auth::id()])->save();

        $mensaje = $config->activa
            ? 'Guardado. La medición está activa en los dominios permitidos.'
            : 'Guardado, pero la medición sigue desactivada: el sitio no carga nada de Google.';

        return redirect()->route('admin.configuracion.medicion.edit')
            ->with('status', $mensaje.$this->resumenDetectado($detectado));
    }

    /**
     * Si llegó `codigo_pegado`, vuelca sobre la petición los IDs que detecte (sobrescriben lo
     * escrito a mano) y borra el texto de la petición para que no se guarde ni se reimprima.
     *
     * @return array{gtm: ?string, ga4: ?string, ads: ?string, conversiones: list<string>}|null
     */
    private function absorberCodigoPegado(Request $request): ?array
    {
        $texto = $request->input('codigo_pegado');
        $request->request->remove('codigo_pegado');

        if (! is_string($texto) || trim($texto) === '') {
            return null;
        }

        if (strlen($texto) > 100000) {
            throw ValidationException::withMessages([
                'codigo_pegado' => 'El texto pegado es demasiado largo. Pega solo el bloque de código que te da Google.',
            ]);
        }

        $detectado = ExtractorEtiquetasGoogle::desde($texto);

        if ($detectado['gtm'] === null && $detectado['ga4'] === null && $detectado['ads'] === null && $detectado['conversiones'] === []) {
            throw ValidationException::withMessages([
                'codigo_pegado' => 'No encontramos ningún ID de Google en lo que pegaste. Busca uno como GTM-ABC1234, G-ABCDE12345 o AW-123456789.',
            ]);
        }

        $mezcla = array_filter([
            'gtm_id' => $detectado['gtm'],
            'ga4_id' => $detectado['ga4'],
            'google_ads_id' => $detectado['ads'],
        ]);

        // Una etiqueta de conversión no dice a qué evento pertenece: solo se asigna si el
        // formulario indica el destino. Si no, se avisa de cuáles se encontraron.
        $destino = self::DESTINOS_CONVERSION[(string) $request->input('conversion_destino')] ?? null;
        if ($destino !== null && $detectado['conversiones'] !== []) {
            $mezcla[$destino] = $detectado['conversiones'][0];
        }

        $request->merge($mezcla);

        return $detectado;
    }

    /** Solo muestra IDs que pasaron los formatos; nunca el texto pegado. */
    private function resumenDetectado(?array $detectado): string
    {
        if ($detectado === null) {
            return '';
        }

        $ids = array_filter([$detectado['gtm'], $detectado['ga4'], $detectado['ads']]);
        $partes = [];

        if ($ids !== []) {
            $partes[] = 'Detectado en el código pegado: '.implode(', ', $ids).'.';
        }
        if ($detectado['conversiones'] !== []) {
            $partes[] = 'Etiquetas de conversión encontradas: '.implode(', ', $detectado['conversiones'])
                .'. Cópialas en el evento que corresponda (agendar, WhatsApp o teléfono).';
        }

        return $partes === [] ? '' : ' '.implode(' ', $partes);
    }

    /** Minúsculas, sin esquema/ruta/puerto, sin repetidos y separados por coma. */
    private function normalizarDominios(?string $dominios): string
    {
        $lista = Medicion::dominiosPermitidos($dominios);

        foreach ($lista as $dominio) {
            if (preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $dominio) !== 1) {
                throw ValidationException::withMessages([
                    'dominios' => 'El dominio «'.$dominio.'» no es válido. Escribe solo el dominio, por ejemplo: rankprosolutions.com.mx',
                ]);
            }
        }

        $texto = implode(', ', $lista);

        if (strlen($texto) > 255) {
            throw ValidationException::withMessages(['dominios' => 'La lista de dominios es demasiado larga (máximo 255 caracteres).']);
        }

        return $texto;
    }

    /** @return array<string, string> */
    private function mensajes(): array
    {
        $e = FormatosGoogle::ejemplos();

        return [
            'gtm_id.regex' => 'El ID de Google Tag Manager debe verse así: '.$e['gtm'],
            'ga4_id.regex' => 'El ID de Google Analytics 4 debe verse así: '.$e['ga4'],
            'google_ads_id.regex' => 'El ID de Google Ads debe verse así: '.$e['ads'],
            'conversion_agendar.regex' => 'La etiqueta de conversión debe verse así: '.$e['conversion'],
            'conversion_whatsapp.regex' => 'La etiqueta de conversión debe verse así: '.$e['conversion'],
            'conversion_telefono.regex' => 'La etiqueta de conversión debe verse así: '.$e['conversion'],
            'dominios.max' => 'La lista de dominios es demasiado larga (máximo 255 caracteres).',
            'activa.boolean' => 'El valor de «Activar medición» no es válido.',
        ];
    }
}
