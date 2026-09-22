<?php

namespace App\Exceptions;

/**
 * Error de negocio de una máquina de fases (checklist incompleto, campaña
 * cerrada, reporte del ciclo sin aprobar...). Los controladores web lo
 * capturan y hacen back()->withErrors([$e->campo => $e->getMessage()]); los
 * de API dejan que el Handler lo convierta a 422 {message, campo}.
 */
class ErrorDeFase extends ErrorDeDominio
{
}
