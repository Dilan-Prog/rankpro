<?php

namespace App\Services\SearchConsole;

use App\Models\GscConexion;
use App\Support\SearchConsole\Saneador;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;

/**
 * Cliente mínimo de la API de Search Console con cuenta de servicio. Sin SDK de Google (el hosting
 * no necesita otra dependencia): el JWT RS256 se firma con openssl y las peticiones salen por Http.
 * Los errores de Google se convierten a ErrorSearchConsole con mensaje en español y saneado.
 */
class ClienteSearchConsole
{
    public const URL_TOKEN = 'https://oauth2.googleapis.com/token';

    public const URL_BASE = 'https://www.googleapis.com/webmasters/v3';

    public const SCOPE = 'https://www.googleapis.com/auth/webmasters.readonly';

    public const FILAS_POR_PAGINA = 25000;

    /** @param  array{client_email: string, private_key: string}  $credenciales */
    public function __construct(private readonly array $credenciales) {}

    public static function desdeConexion(?GscConexion $conexion = null): self
    {
        $credenciales = ($conexion ?? GscConexion::actual())?->credenciales();

        if ($credenciales === null) {
            throw new ErrorSearchConsole('Aún no hay llave de Google guardada (o no se pudo leer). Pega el JSON de la cuenta de servicio en Integraciones > Search Console.');
        }

        return new self($credenciales);
    }

    public function correo(): string
    {
        return $this->credenciales['client_email'];
    }

    /** JWT RS256 para el grant jwt-bearer; público para poder verificar la firma en tests. */
    public function firmarJwt(?int $ahora = null): string
    {
        $ahora ??= time();
        $b64 = fn (string $s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');

        $cabecera = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $reclamos = $b64(json_encode([
            'iss' => $this->credenciales['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::URL_TOKEN,
            'iat' => $ahora,
            'exp' => $ahora + 3600,
        ]));

        $llave = @openssl_pkey_get_private($this->credenciales['private_key']);
        if ($llave === false) {
            while (openssl_error_string() !== false) {
                // Vacía la cola de errores de openssl: sus mensajes no deben acabar en ningún lado.
            }
            throw new ErrorSearchConsole('La llave privada del JSON no es válida: vuelve a descargar la llave desde Google Cloud.');
        }

        $firma = '';
        if (! openssl_sign("{$cabecera}.{$reclamos}", $firma, $llave, OPENSSL_ALGO_SHA256)) {
            throw new ErrorSearchConsole('No se pudo firmar la solicitud con la llave privada.');
        }

        return "{$cabecera}.{$reclamos}.".$b64($firma);
    }

    /** Token de acceso, cacheado (cifrado) hasta 2 minutos antes de que expire. */
    public function token(): string
    {
        $clave = $this->claveCache();
        $guardado = Cache::get($clave);
        if (is_string($guardado)) {
            try {
                return Crypt::decryptString($guardado);
            } catch (\Throwable) {
                Cache::forget($clave);
            }
        }

        try {
            $respuesta = Http::asForm()->timeout(20)->post(self::URL_TOKEN, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->firmarJwt(),
            ]);
        } catch (ConnectionException) {
            throw new ErrorSearchConsole('No se pudo conectar con Google para obtener el acceso. Revisa la conexión a internet del servidor.');
        }

        if (! $respuesta->successful() || ! is_string($respuesta->json('access_token'))) {
            $detalle = Saneador::mensaje((string) ($respuesta->json('error_description') ?? $respuesta->json('error') ?? ''), 160, [$this->credenciales['private_key']]);

            throw new ErrorSearchConsole(
                'Google rechazó la llave de la cuenta de servicio'.($detalle !== '' ? " ({$detalle})" : '')
                .'. Revisa que la llave no esté revocada y que el reloj del servidor sea correcto.',
                $respuesta->status()
            );
        }

        $vida = max(60, (int) $respuesta->json('expires_in', 3600) - 120);
        Cache::put($clave, Crypt::encryptString($respuesta->json('access_token')), $vida);

        return $respuesta->json('access_token');
    }

    public function olvidarToken(): void
    {
        Cache::forget($this->claveCache());
    }

    /**
     * Propiedades a las que la cuenta de servicio tiene acceso.
     *
     * @return array<int, array{site_url: string, permiso: string}>
     */
    public function propiedades(): array
    {
        $json = $this->enviar('GET', self::URL_BASE.'/sites', null, null);

        return collect($json['siteEntry'] ?? [])
            ->map(fn ($e) => ['site_url' => (string) ($e['siteUrl'] ?? ''), 'permiso' => (string) ($e['permissionLevel'] ?? '')])
            ->filter(fn ($e) => $e['site_url'] !== '')
            ->values()
            ->all();
    }

    /**
     * searchAnalytics.query paginado con startRow. Para en la primera página incompleta o al llegar a $maxPaginas.
     *
     * @param  array<int, string>  $dimensiones
     * @return array<int, array{keys: array<int, string>, clics: int, impresiones: int, ctr: float, posicion: float}>
     */
    public function consultar(string $siteUrl, string $inicio, string $fin, array $dimensiones, string $dataState = 'final', int $maxPaginas = 10, int $filasPorPagina = self::FILAS_POR_PAGINA): array
    {
        $filas = [];
        $url = self::URL_BASE.'/sites/'.rawurlencode($siteUrl).'/searchAnalytics/query';

        for ($pagina = 0; $pagina < $maxPaginas; $pagina++) {
            $json = $this->enviar('POST', $url, [
                'startDate' => $inicio,
                'endDate' => $fin,
                'dimensions' => $dimensiones,
                'rowLimit' => $filasPorPagina,
                'startRow' => $pagina * $filasPorPagina,
                'dataState' => $dataState,
            ], $siteUrl);

            $recibidas = $json['rows'] ?? [];
            foreach ($recibidas as $f) {
                $filas[] = [
                    'keys' => array_map('strval', $f['keys'] ?? []),
                    'clics' => (int) round($f['clicks'] ?? 0),
                    'impresiones' => (int) round($f['impressions'] ?? 0),
                    'ctr' => (float) ($f['ctr'] ?? 0),
                    'posicion' => (float) ($f['position'] ?? 0),
                ];
            }

            if (count($recibidas) < $filasPorPagina) {
                break;
            }
        }

        return $filas;
    }

    /** @return array<string, mixed> */
    private function enviar(string $metodo, string $url, ?array $cuerpo, ?string $siteUrl): array
    {
        $maxIntentos = 3;
        $esperaMs = (int) config('services.google_search_console.espera_ms', 800);

        for ($intento = 1; ; $intento++) {
            try {
                $peticion = Http::withToken($this->token())->acceptJson()->timeout(60);
                $respuesta = $metodo === 'GET' ? $peticion->get($url) : $peticion->post($url, $cuerpo ?? []);
            } catch (ConnectionException) {
                if ($intento < $maxIntentos) {
                    $this->esperar($esperaMs, $intento);

                    continue;
                }
                throw new ErrorSearchConsole('No se pudo conectar con Google. Revisa la conexión a internet del servidor e inténtalo de nuevo.');
            }

            // Cuota (429) y fallos temporales (5xx) se reintentan un número acotado de veces.
            if (in_array($respuesta->status(), [429, 500, 502, 503, 504], true) && $intento < $maxIntentos) {
                $this->esperar($esperaMs, $intento);

                continue;
            }

            if ($respuesta->successful()) {
                return (array) $respuesta->json();
            }

            // Un 401 con token cacheado suele ser un token revocado: se descarta para pedir otro en la siguiente llamada.
            if ($respuesta->status() === 401) {
                $this->olvidarToken();
            }

            throw $this->traducirError($respuesta, $siteUrl);
        }
    }

    private function esperar(int $esperaMs, int $intento): void
    {
        if ($esperaMs > 0) {
            usleep($esperaMs * 1000 * $intento);
        }
    }

    private function traducirError(Response $r, ?string $siteUrl): ErrorSearchConsole
    {
        $estado = $r->status();
        $razon = strtolower((string) ($r->json('error.errors.0.reason') ?? $r->json('error.details.0.reason') ?? ''));
        $detalle = Saneador::mensaje((string) ($r->json('error.message') ?? ''), 160, [$this->credenciales['private_key']]);

        if ($estado === 403 && (str_contains($razon, 'service_disabled') || str_contains($razon, 'accessnotconfigured'))) {
            return new ErrorSearchConsole('La "Google Search Console API" no está habilitada en el proyecto de Google Cloud de la cuenta de servicio. Habilítala en APIs y servicios > Biblioteca.', $estado);
        }

        return match (true) {
            $estado === 401, $estado === 403 => new ErrorSearchConsole(
                'La cuenta de servicio no tiene acceso a '.($siteUrl !== null ? "la propiedad {$siteUrl}" : 'Search Console')
                .'. Añade el correo '.$this->credenciales['client_email'].' como usuario (permiso "Restringido" basta) en la propiedad de Search Console.',
                $estado
            ),
            $estado === 404 => new ErrorSearchConsole(
                'Google no encontró la propiedad'.($siteUrl !== null ? " {$siteUrl}" : '').'. Usa el valor exacto que aparece en Search Console: https://dominio.com/ (con barra final) o sc-domain:dominio.com.',
                $estado
            ),
            $estado === 429 => new ErrorSearchConsole('Google limitó las peticiones (cuota excedida) y no cedió tras varios reintentos. Inténtalo de nuevo en unos minutos.', $estado),
            $estado >= 500 => new ErrorSearchConsole("Google tuvo un error temporal ({$estado}) y no se recuperó tras varios reintentos. Inténtalo más tarde.", $estado),
            default => new ErrorSearchConsole("Google rechazó la consulta ({$estado})".($detalle !== '' ? ": {$detalle}" : '.'), $estado),
        };
    }

    private function claveCache(): string
    {
        return 'gsc:token:'.sha1($this->credenciales['client_email'].'|'.sha1($this->credenciales['private_key']));
    }
}
