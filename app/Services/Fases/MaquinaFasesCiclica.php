<?php

namespace App\Services\Fases;

use App\Enums\EstadoCampana;
use App\Exceptions\ErrorDeFase;
use Illuminate\Database\Eloquent\Model;

/**
 * Máquina de estados compartida por SEO, Ads y Automatizaciones: 3 servicios
 * de fase (Services\Fases\Maquina{Seo,Ads,Automatizacion}) que solo declaran
 * las diferencias (relaciones, checklists, textos) — ver esas clases. Extraída
 * de SeoFaseController/AdsFaseController/AutomatizacionFaseController, que
 * eran copias casi idénticas de esto mismo (confirmado línea a línea).
 *
 * Cubierta por tests/Feature/Admin/FasesCaracterizacionTest.php, escrito
 * ANTES de este refactor contra el código original: si algo aquí rompe ese
 * test, el refactor cambió comportamiento, no el test.
 *
 * `guardar()` (el autosave del formulario de la fase) se queda en el
 * controlador de cada dominio: la forma de los campos por fase es demasiado
 * distinta como para generalizarla sin perder legibilidad, y no es la parte
 * arriesgada (no hay máquina de estados ahí, solo un $request->validate()
 * por fase). Lo que sí se comparte —fusionar el checklist recibido con el
 * existente— vive en fusionarChecklist(), que guardar() sí usa.
 */
abstract class MaquinaFasesCiclica
{
    /** Nombre en los mensajes: 'La campaña' / 'El proyecto'. */
    abstract protected function sujeto(): string;

    /** Femenino en los mensajes ('a' en "cerrada"/"pausada") o vacío. */
    abstract protected function generoNeutro(): string;

    abstract protected function faseInicial(): \BackedEnum;

    abstract protected function faseCerrada(): \BackedEnum;

    abstract protected function faseReporte(): \BackedEnum;

    /** Fila de la fase actual (o la crea si no existe). Público: guardar() de cada controlador también lo usa. */
    abstract public function registro(Model $modelo, \BackedEnum $fase): Model;

    /** Claves del checklist ('clave' => 'etiqueta') de una fase. */
    abstract protected function checklistKeys(\BackedEnum $fase): array;

    /** Crea las 4 filas del ciclo nuevo. */
    abstract protected function crearCiclo(Model $modelo, int $ciclo): void;

    protected function reporteActual(Model $modelo): ?Model
    {
        return $this->registroSiExiste($modelo, $this->faseReporte());
    }

    /** Como registro() pero sin crear la fila si no existe (para no ensuciar el ciclo al solo consultar). */
    abstract protected function registroSiExiste(Model $modelo, \BackedEnum $fase): ?Model;

    public function checklistCompleto(Model $registro, \BackedEnum $fase): bool
    {
        $checklist = $registro->checklist ?? [];

        foreach (array_keys($this->checklistKeys($fase)) as $clave) {
            if (empty($checklist[$clave])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Fusiona el checklist recibido con el existente: una clave no enviada
     * conserva su valor actual (no se "des-marca" sola). Usado por guardar().
     *
     * @param  array<string, mixed>  $checklistRecibido
     * @return array<string, bool>
     */
    public function fusionarChecklist(Model $registro, \BackedEnum $fase, array $checklistRecibido): array
    {
        $actual = $registro->checklist ?? [];

        $out = [];
        foreach (array_keys($this->checklistKeys($fase)) as $clave) {
            $out[$clave] = (bool) ($checklistRecibido[$clave] ?? $actual[$clave] ?? false);
        }

        return $out;
    }

    private function reporteListo(Model $modelo): bool
    {
        return $modelo->fase_actual === $this->faseReporte() && (bool) $this->reporteActual($modelo)?->aprobado;
    }

    public function aprobar(Model $modelo): ResultadoFase
    {
        $fase = $modelo->fase_actual;

        if ($fase === $this->faseCerrada()) {
            throw new ErrorDeFase("{$this->sujeto()} está cerrad{$this->generoNeutro()}.", 'fase');
        }

        $registro = $this->registro($modelo, $fase);

        if (! $this->checklistCompleto($registro, $fase)) {
            throw new ErrorDeFase('Completa todo el checklist antes de aprobar esta fase.', 'checklist');
        }

        $registro->update(['aprobado' => true, 'fecha_aprobacion' => now()]);

        $siguiente = $fase->siguiente();

        if ($siguiente === null) {
            return new ResultadoFase($modelo->fresh(), 'Reporte aprobado. Elige cómo continuar.');
        }

        $modelo->fase_actual = $siguiente;
        $modelo->save();

        return new ResultadoFase($modelo->fresh(), 'Fase aprobada. Avanzó a la siguiente etapa.');
    }

    public function retroceder(Model $modelo): ResultadoFase
    {
        $anterior = $modelo->fase_actual->anterior();

        if ($anterior === null) {
            throw new ErrorDeFase("{$this->sujeto()} ya está en la primera fase o está cerrad{$this->generoNeutro()}.", 'fase');
        }

        $this->registro($modelo, $anterior)->update(['aprobado' => false, 'fecha_aprobacion' => null]);

        $modelo->fase_actual = $anterior;
        $modelo->save();

        return new ResultadoFase($modelo->fresh(), 'Retrocedió a la fase anterior.');
    }

    public function nuevoCiclo(Model $modelo): ResultadoFase
    {
        if (! $this->reporteListo($modelo)) {
            throw new ErrorDeFase('Aprueba el reporte del ciclo actual antes de iniciar uno nuevo.', 'fase');
        }

        $nuevo = $modelo->ciclo_actual + 1;
        $this->crearCiclo($modelo, $nuevo);

        $modelo->fase_actual = $this->faseInicial();
        $modelo->ciclo_actual = $nuevo;
        $modelo->save();

        return new ResultadoFase($modelo->fresh(), "Ciclo {$nuevo} iniciado.");
    }

    public function cerrar(Model $modelo): ResultadoFase
    {
        if (! $this->reporteListo($modelo)) {
            throw new ErrorDeFase('Aprueba el reporte del ciclo actual antes de cerrar.', 'fase');
        }

        $modelo->fase_actual = $this->faseCerrada();
        $modelo->estado = EstadoCampana::Finalizada;
        $modelo->save();

        return new ResultadoFase($modelo->fresh(), 'Cerrada.');
    }

    public function pausar(Model $modelo): ResultadoFase
    {
        if (! $this->reporteListo($modelo)) {
            throw new ErrorDeFase('Aprueba el reporte del ciclo actual antes de pausar.', 'fase');
        }

        $modelo->estado = EstadoCampana::Pausada;
        $modelo->save();

        return new ResultadoFase($modelo->fresh(), 'Pausada.');
    }

    /**
     * Estado consultable de la fase actual (para GET .../fase de la API).
     *
     * @return array<string, mixed>
     */
    public function estado(Model $modelo): array
    {
        $fase = $modelo->fase_actual;
        $cerrada = $fase === $this->faseCerrada();
        $registro = $cerrada ? null : $this->registro($modelo, $fase);

        return [
            'fase_actual' => $fase->value,
            'ciclo_actual' => $modelo->ciclo_actual,
            'estado' => $modelo->estado->value,
            'checklist' => $registro?->checklist ?? [],
            'completo' => $registro ? $this->checklistCompleto($registro, $fase) : false,
            'puede' => [
                'aprobar' => ! $cerrada,
                'retroceder' => ! $cerrada && $fase->anterior() !== null,
                'nuevo_ciclo' => $this->reporteListo($modelo),
                'cerrar' => $this->reporteListo($modelo),
                'pausar' => $this->reporteListo($modelo),
            ],
        ];
    }
}
