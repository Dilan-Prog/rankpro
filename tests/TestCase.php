<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Casi todos los tests entran con usuarios sin rol; los permisos por módulo
     * se prueban aparte (PermisosTest) encendiéndolos a propósito.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config(['permisos.aplicar' => false]);
    }
}
