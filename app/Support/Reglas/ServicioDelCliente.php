<?php

namespace App\Support\Reglas;

use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * `servicio_id` debe existir (no borrado), ser del `cliente_id` enviado y ser
 * de uno de los tipos que admite el módulo. `exists:servicios,id` solo miraba
 * que la fila existiera: se podía crear una campaña SEO con el servicio de
 * otro cliente, con uno de otro tipo o con uno ya eliminado (soft delete).
 */
class ServicioDelCliente implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<int, string>  $tipos  valores de App\Enums\TipoServicio admitidos
     * @param  string  $modulo  texto para el mensaje: 'SEO', 'Ads', 'Automatizaciones'
     */
    public function __construct(private array $tipos, private string $modulo)
    {
    }

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $servicio = DB::table('servicios')->where('id', $value)->whereNull('deleted_at')->first();

        if (! $servicio) {
            $fail('El servicio seleccionado no existe o fue eliminado.');

            return;
        }

        if (! in_array($servicio->tipo, $this->tipos, true)) {
            $fail("El servicio seleccionado no es un servicio de {$this->modulo}.");

            return;
        }

        if (isset($this->data['cliente_id']) && (int) $servicio->cliente_id !== (int) $this->data['cliente_id']) {
            $fail('El servicio seleccionado pertenece a otro cliente.');
        }
    }
}
