<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\GscConexion;
use App\Models\GscPropiedad;
use App\Services\SearchConsole\ClienteSearchConsole;
use App\Services\SearchConsole\ErrorSearchConsole;
use App\Services\SearchConsole\Sincronizador;
use App\Services\SearchConsole\SincronizacionEnCurso;
use App\Support\SearchConsole\Saneador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

/**
 * Integración directa con Google Search Console (cuenta de servicio): llave, propiedades por cliente
 * y sincronización manual. Formularios clásicos con redirect; si la petición pide JSON responde JSON
 * (así los tests y un eventual JS comparten ruta). La llave privada nunca se devuelve ni se refleja:
 * solo el correo de la cuenta de servicio y "llave guardada".
 */
class SearchConsoleController extends Controller
{
    public function index(): View
    {
        $conexion = GscConexion::actual();

        $clientes = Cliente::query()->orderBy('nombre')->get(['id', 'nombre', 'empresa', 'estado']);
        $propiedades = GscPropiedad::query()->with(['metricas' => fn ($q) => $q->limit(6)])->get()->keyBy('cliente_id');

        return view('admin.integraciones.search-console', [
            'pageTitle' => 'Search Console',
            'conexion' => $conexion,
            'tieneLlave' => (bool) $conexion?->tieneLlave(),
            'clientes' => $clientes,
            'propiedades' => $propiedades,
            'detectadas' => collect($conexion?->propiedades_detectadas ?? []),
        ]);
    }

    public function guardarLlave(Request $request): RedirectResponse|JsonResponse
    {
        $validador = Validator::make($request->all(), [
            'llave_json' => ['nullable', 'string', 'max:20000'],
            'llave_archivo' => ['nullable', 'file', 'max:20'],
        ], [
            'llave_json.max' => 'El JSON es demasiado grande para ser la llave de una cuenta de servicio.',
            'llave_archivo.max' => 'El archivo pesa demasiado para ser la llave de una cuenta de servicio (máximo 20 KB).',
        ]);

        $texto = $request->hasFile('llave_archivo') && $request->file('llave_archivo')->isValid()
            ? (string) file_get_contents($request->file('llave_archivo')->getRealPath())
            : (string) $request->input('llave_json', '');

        if (! $validador->fails() && trim($texto) === '') {
            $validador->errors()->add('llave_json', 'Pega el contenido del JSON o sube el archivo descargado de Google Cloud.');
        }

        $llave = null;
        if ($validador->errors()->isEmpty()) {
            [$llave, $error] = $this->interpretarLlave($texto);
            if ($error !== null) {
                $validador->errors()->add('llave_json', $error);
            }
        }

        if ($validador->errors()->isNotEmpty()) {
            // Sin withInput() a propósito: la llave no debe quedar en la sesión.
            return $request->expectsJson()
                ? response()->json(['message' => $validador->errors()->first(), 'errors' => $validador->errors()], 422)
                : redirect()->route('admin.integraciones.search-console.index')->withErrors($validador->errors());
        }

        $anterior = GscConexion::actual();
        if ($anterior?->tieneLlave()) {
            try {
                ClienteSearchConsole::desdeConexion($anterior)->olvidarToken();
            } catch (\Throwable) {
                // Llave anterior ilegible: no hay token que olvidar.
            }
        }

        ($anterior ?? new GscConexion())->forceFill([
            'id' => 1,
            'client_email' => $llave['client_email'],
            'llave_json' => json_encode($llave),
            'estado' => GscConexion::PENDIENTE,
            'ultima_prueba_en' => null,
            'ultima_prueba_ok' => null,
            'ultima_prueba_resultado' => null,
            'propiedades_detectadas' => null,
            'actualizado_por' => Auth::id(),
        ])->save();

        return $this->responder($request, true, 'Llave guardada (cifrada). Ahora pulsa "Probar conexión" para ver qué propiedades alcanza.', extra: ['client_email' => $llave['client_email']]);
    }

    public function quitarLlave(Request $request): RedirectResponse|JsonResponse
    {
        $conexion = GscConexion::actual();

        if ($conexion?->tieneLlave()) {
            try {
                ClienteSearchConsole::desdeConexion($conexion)->olvidarToken();
            } catch (\Throwable) {
                // Nada que olvidar.
            }
            $conexion->forceFill([
                'client_email' => null,
                'llave_json' => null,
                'estado' => GscConexion::SIN_LLAVE,
                'ultima_prueba_en' => null,
                'ultima_prueba_ok' => null,
                'ultima_prueba_resultado' => null,
                'propiedades_detectadas' => null,
                'actualizado_por' => Auth::id(),
            ])->save();
        }

        return $this->responder($request, true, 'Llave eliminada. Las sincronizaciones quedan detenidas hasta que pegues otra.');
    }

    public function probar(Request $request): RedirectResponse|JsonResponse
    {
        $conexion = GscConexion::actual();
        if (! $conexion?->tieneLlave()) {
            return $this->responder($request, false, 'Aún no hay llave guardada: pega el JSON de la cuenta de servicio primero.', 422);
        }

        try {
            $api = ClienteSearchConsole::desdeConexion($conexion);
            // Se descarta el token cacheado para que la prueba valide la llave de verdad, no un token vivo.
            $api->olvidarToken();
            $sitios = array_column($api->propiedades(), 'site_url');

            $mensaje = $sitios === []
                ? 'La llave funciona, pero la cuenta de servicio aún no tiene ninguna propiedad. Añade '.$conexion->client_email.' como usuario en cada propiedad de Search Console.'
                : 'Conexión correcta: '.count($sitios).' '.(count($sitios) === 1 ? 'propiedad accesible' : 'propiedades accesibles').'.';
            $ok = true;
        } catch (ErrorSearchConsole $e) {
            $sitios = [];
            $mensaje = $e->getMessage();
            $ok = false;
        }

        $conexion->forceFill([
            'estado' => $ok ? GscConexion::OK : GscConexion::ERROR,
            'ultima_prueba_en' => now(),
            'ultima_prueba_ok' => $ok,
            'ultima_prueba_resultado' => Saneador::mensaje($mensaje, 400),
            // Si falla se conserva la lista anterior: sigue siendo útil para el selector.
            'propiedades_detectadas' => $ok ? $sitios : $conexion->propiedades_detectadas,
        ])->save();

        return $this->responder($request, $ok, Saneador::mensaje($mensaje, 400), $ok ? 200 : 502, ['propiedades' => $sitios]);
    }

    public function guardarPropiedad(Request $request, Cliente $cliente): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'site_url' => ['required', 'string', 'max:255', 'regex:#^(https?://[^\s]+|sc-domain:[A-Za-z0-9.\-]+)$#'],
            'activa' => ['nullable', 'boolean'],
        ], [
            'site_url.regex' => 'Escribe la propiedad como aparece en Search Console: https://dominio.com/ o sc-domain:dominio.com.',
        ]);

        $siteUrl = trim($data['site_url']);
        // Una propiedad de prefijo de URL siempre lleva barra final en Search Console.
        if (str_starts_with($siteUrl, 'http') && (parse_url($siteUrl, PHP_URL_PATH) === null)) {
            $siteUrl .= '/';
        }

        $propiedad = GscPropiedad::firstOrNew(['cliente_id' => $cliente->id]);
        $cambioSitio = $propiedad->exists && $propiedad->site_url !== $siteUrl;

        $propiedad->forceFill([
            'site_url' => $siteUrl,
            'activa' => $request->has('activa') ? $request->boolean('activa') : ($propiedad->exists ? $propiedad->activa : true),
        ]);

        if ($cambioSitio) {
            // Los totales eran de otro sitio: mezclarlos con los del nuevo falsearía la serie.
            $propiedad->metricas()->delete();
            $propiedad->ultima_sync_en = $propiedad->ultimo_ok = $propiedad->ultimo_resultado = null;
        }
        $propiedad->save();

        return $this->responder($request, true, "Propiedad de {$cliente->nombre} guardada.", extra: ['propiedad' => $propiedad->toRow()]);
    }

    public function quitarPropiedad(Request $request, GscPropiedad $propiedad): RedirectResponse|JsonResponse
    {
        $propiedad->delete();

        return $this->responder($request, true, 'Propiedad desvinculada. Las mediciones de posición ya creadas se conservan.');
    }

    public function sincronizar(Request $request, GscPropiedad $propiedad, Sincronizador $sincronizador): RedirectResponse|JsonResponse
    {
        if (! $propiedad->activa) {
            return $this->responder($request, false, 'La propiedad está pausada: actívala para sincronizar.', 422);
        }

        // Una primera importación de 16 meses puede tardar más que el límite habitual de PHP.
        @set_time_limit(300);

        try {
            $r = $sincronizador->sincronizar($propiedad);
        } catch (SincronizacionEnCurso $e) {
            return $this->responder($request, false, $e->getMessage(), 409);
        }

        return $this->responder($request, $r['ok'], $r['mensaje'], $r['ok'] ? 200 : 502);
    }

    /** @return array{0: array<string, string>|null, 1: string|null} [llave mínima, error] */
    private function interpretarLlave(string $texto): array
    {
        $json = json_decode(trim($texto), true);

        if (! is_array($json)) {
            return [null, 'Eso no es un JSON válido. Pega el archivo completo que descargaste de Google Cloud.'];
        }
        if (($json['type'] ?? null) !== 'service_account') {
            return [null, 'El JSON no es de una cuenta de servicio (falta "type": "service_account"). Crea la llave en IAM > Cuentas de servicio, no una credencial OAuth.'];
        }
        if (! is_string($json['client_email'] ?? null) || ! filter_var($json['client_email'], FILTER_VALIDATE_EMAIL)) {
            return [null, 'El JSON no trae un "client_email" válido.'];
        }
        $privada = $json['private_key'] ?? null;
        if (! is_string($privada) || ! str_contains($privada, 'PRIVATE KEY')) {
            return [null, 'El JSON no trae la "private_key" de la cuenta de servicio.'];
        }
        if (@openssl_pkey_get_private($privada) === false) {
            while (openssl_error_string() !== false) {
                // Vacía la cola de errores de openssl.
            }

            return [null, 'La "private_key" del JSON no se pudo leer como llave RSA. Vuelve a descargar la llave.'];
        }

        // Solo lo necesario: el resto del JSON (project_id, client_id…) no hace falta para firmar.
        return [['client_email' => $json['client_email'], 'private_key' => $privada], null];
    }

    private function responder(Request $request, bool $ok, string $mensaje, int $estado = 200, array $extra = []): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok, 'message' => $mensaje] + $extra, $estado);
        }

        return redirect()->route('admin.integraciones.search-console.index')->with($ok ? 'status' : 'error', $mensaje);
    }
}
