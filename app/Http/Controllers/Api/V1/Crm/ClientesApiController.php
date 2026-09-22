<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Api\V1\ControladorApi;
use App\Models\AdsClic;
use App\Models\AdsConversion;
use App\Models\Archivo;
use App\Models\Cliente;
use App\Models\Finanza;
use App\Models\Keyword;
use App\Models\Servicio;
use App\Support\Api\ConsultaOpciones;
use App\Support\Api\Respuesta;
use App\Support\Api\Serializador;
use App\Support\Reglas\Clientes as ReglasClientes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * CRUD de clientes + subrecursos de solo lectura (servicios, finanzas,
 * archivos, keywords, clics, conversiones) para que n8n pueda leer todo el
 * expediente de un cliente sin pedir cada módulo por separado.
 */
class ClientesApiController extends ControladorApi
{
    public function index(Request $request)
    {
        return $this->listar(Cliente::query(), $request, new ConsultaOpciones(
            buscarEn: ['nombre', 'empresa', 'email'],
            filtrosExactos: ['estado'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
            incluibles: ['servicios'],
        ));
    }

    public function show(Cliente $cliente)
    {
        return Respuesta::recurso(Serializador::modelo($cliente));
    }

    public function store(Request $request)
    {
        $cliente = Cliente::create($request->validate(ReglasClientes::guardar()));

        return Respuesta::recurso(Serializador::modelo($cliente), 201);
    }

    public function update(Request $request, Cliente $cliente)
    {
        $cliente->update($request->validate(ReglasClientes::guardar($cliente)));

        return Respuesta::recurso(Serializador::modelo($cliente->fresh()));
    }

    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return Respuesta::eliminado();
    }

    public function servicios(Request $request, Cliente $cliente)
    {
        return $this->listar(Servicio::where('cliente_id', $cliente->id), $request, new ConsultaOpciones(
            buscarEn: ['nombre'],
            filtrosExactos: ['estado', 'tipo', 'responsable_id'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
        ));
    }

    public function finanzas(Request $request, Cliente $cliente)
    {
        return $this->listar(Finanza::where('cliente_id', $cliente->id), $request, new ConsultaOpciones(
            buscarEn: ['concepto'],
            filtrosExactos: ['estado', 'tipo', 'servicio_id'],
            ordenables: ['id', 'fecha_emision', 'monto', 'created_at', 'updated_at'],
            ordenPorDefecto: '-fecha_emision',
        ));
    }

    public function archivos(Request $request, Cliente $cliente)
    {
        return $this->listar(Archivo::where('cliente_id', $cliente->id), $request, new ConsultaOpciones(
            buscarEn: ['nombre'],
            filtrosExactos: ['tipo'],
            ordenables: ['id', 'nombre', 'created_at', 'updated_at'],
        ));
    }

    public function keywords(Request $request, Cliente $cliente)
    {
        return $this->listar(Keyword::where('cliente_id', $cliente->id), $request, new ConsultaOpciones(
            buscarEn: ['keyword'],
            filtrosExactos: ['estado', 'campana_id', 'lista_id'],
            ordenables: ['id', 'keyword', 'posicion_actual', 'created_at', 'updated_at'],
        ));
    }

    public function clics(Request $request, Cliente $cliente)
    {
        return $this->listar(AdsClic::where('cliente_id', $cliente->id), $request, new ConsultaOpciones(
            filtrosExactos: ['ads_campana_id'],
            ordenables: ['id', 'created_at'],
            ordenPorDefecto: '-created_at',
        ));
    }

    public function conversiones(Request $request, Cliente $cliente)
    {
        return $this->listar(AdsConversion::where('cliente_id', $cliente->id), $request, new ConsultaOpciones(
            filtrosExactos: ['estado', 'tipo', 'ads_clic_id', 'ads_embudo_etapa_id'],
            ordenables: ['id', 'created_at', 'updated_at'],
            ordenPorDefecto: '-created_at',
        ));
    }

    /**
     * Regenera el api_token de tracking del cliente (misma lógica que
     * Admin\IntegracionesController::regenerarToken) y lo devuelve UNA VEZ:
     * el modelo lo oculta ($hidden) en cualquier otra serialización.
     */
    public function token(Cliente $cliente)
    {
        $cliente->forceFill([
            'api_token' => 'rp_live_'.Str::random(56),
            'api_token_regenerated_at' => now(),
        ])->save();

        return Respuesta::recurso(['api_token' => $cliente->api_token]);
    }
}
