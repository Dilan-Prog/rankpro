<?php

namespace App\Http\Controllers\Api\V1\Conversiones;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\AdsClic;
use App\Models\Cliente;
use App\Support\Api\{ConsultaOpciones, Respuesta, Serializador};
use App\Support\Conversiones\RegistradorConversion;
use Illuminate\Http\Request;

class ClicsApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(AdsClic::query(), $request, new ConsultaOpciones(
            filtrosExactos: ['cliente_id'],
            ordenables: ['id', 'created_at'],
            incluibles: ['cliente', 'adsCampana'],
        ));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cliente_id' => ['required', 'integer', 'exists:clientes,id'],
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
            'user_agent' => ['nullable', 'string', 'max:255'],
            'ip_address' => ['nullable', 'ip'],
        ]);

        $cliente = Cliente::findOrFail($data['cliente_id']);
        unset($data['cliente_id']);

        $clic = RegistradorConversion::registrarClic($cliente, $data);

        return Respuesta::recurso(Serializador::modelo($clic), 201);
    }
}
