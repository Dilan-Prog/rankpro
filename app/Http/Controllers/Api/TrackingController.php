<?php

namespace App\Http\Controllers\Api;

use App\Enums\TipoConversion;
use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Support\Conversiones\RegistradorConversion;
use Closure;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrackingController extends Controller
{
    /** UUID v4 o cadena de 16 a 64 caracteres [A-Za-z0-9_-]. */
    private const REGLA_EVENTO_ID = ['nullable', 'string', 'regex:/^[A-Za-z0-9_-]{16,64}$/'];

    public function storeClic(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->attributes->get('cliente');

        $this->fusionarEventoId($request);

        $data = $request->validate([
            'evento_id' => self::REGLA_EVENTO_ID,
            'visitor_id' => ['required', 'string', 'max:64'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:255'],
            'utm_term' => ['nullable', 'string', 'max:255'],
            'utm_content' => ['nullable', 'string', 'max:255'],
            'landing_url' => ['required', 'string', 'max:2048'],
            'referrer' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['user_agent'] = $request->userAgent();
        $data['ip_address'] = $request->ip();

        return $this->registrarIdempotente(
            $cliente->adsClics(),
            $data['evento_id'] ?? null,
            fn () => RegistradorConversion::registrarClic($cliente, $data)
        );
    }

    public function storeConversion(Request $request): JsonResponse
    {
        /** @var Cliente $cliente */
        $cliente = $request->attributes->get('cliente');

        $this->fusionarEventoId($request);

        $data = $request->validate([
            'evento_id' => self::REGLA_EVENTO_ID,
            'visitor_id' => ['required', 'string', 'max:64'],
            'gclid' => ['nullable', 'string', 'max:255'],
            'gbraid' => ['nullable', 'string', 'max:255'],
            'wbraid' => ['nullable', 'string', 'max:255'],
            'tipo' => ['required', Rule::enum(TipoConversion::class)],
            'valor' => ['nullable', 'numeric', 'min:0'],
            // Tope: el endpoint es público y el JSON se guarda tal cual, sin él se podría
            // inflar la tabla con un solo POST.
            'metadata' => ['nullable', 'array', 'max:20', function ($atributo, $valor, $fallar) {
                if (strlen((string) json_encode($valor)) > 2048) {
                    $fallar('El campo metadata no puede pesar más de 2 KB.');
                }
            }],
        ]);

        return $this->registrarIdempotente(
            $cliente->adsConversiones(),
            $data['evento_id'] ?? null,
            fn () => RegistradorConversion::registrarConversion($cliente, $data)
        );
    }

    /**
     * El header gana sobre el cuerpo: lo puede poner el propio snippet o un
     * proxy sin tocar el JSON. Un header vacío se ignora (se usa el cuerpo).
     */
    private function fusionarEventoId(Request $request): void
    {
        $header = $request->header('X-RankPro-Evento-Id');

        if (is_string($header) && trim($header) !== '') {
            $request->merge(['evento_id' => trim($header)]);
        }
    }

    /**
     * Sin evento_id se comporta como siempre (compatibilidad con snippets ya
     * instalados). Con evento_id, el índice único (cliente_id, evento_id) es
     * quien decide: la consulta previa solo evita un INSERT inútil; si dos
     * peticiones simultáneas pasan la consulta, la segunda choca con el índice
     * y se responde como duplicado en vez de 500. Un duplicado no crea fila,
     * así que el Observer (webhooks) no vuelve a dispararse.
     *
     * @param  HasMany  $relacion  ya acotada al cliente del token (aislamiento por cliente)
     */
    private function registrarIdempotente(HasMany $relacion, ?string $eventoId, Closure $registrar): JsonResponse
    {
        if ($eventoId === null || $eventoId === '') {
            $registrar();

            return response()->json(['ok' => true], 201);
        }

        if ((clone $relacion)->where('evento_id', $eventoId)->exists()) {
            return $this->respuestaDuplicado();
        }

        try {
            $registrar();
        } catch (QueryException $e) {
            // 1062 = entrada duplicada de MySQL: solo puede ser nuestro índice único.
            if (($e->errorInfo[1] ?? null) === 1062) {
                return $this->respuestaDuplicado();
            }

            throw $e;
        }

        return response()->json(['ok' => true], 201);
    }

    /** Mismo código y cuerpo que la primera vez, más la marca de duplicado. */
    private function respuestaDuplicado(): JsonResponse
    {
        return response()->json(['ok' => true, 'duplicado' => true], 201)
            ->header('X-RankPro-Duplicado', '1');
    }

    public function snippet(string $token)
    {
        $cliente = Cliente::whereNotNull('api_token')->where('api_token', $token)->first();

        $js = view('tracking.snippet', [
            'valido' => (bool) $cliente,
            'token' => $token,
            'apiBase' => rtrim(url('/api/tracking'), '/'),
        ])->render();

        return response($js, 200)
            ->header('Content-Type', 'application/javascript; charset=utf-8')
            ->header('Cache-Control', 'public, max-age=300');
    }
}
