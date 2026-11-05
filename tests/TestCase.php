<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /** Corre antes de RefreshDatabase: nunca migrar/borrar una base real. */
    public function createApplication()
    {
        $app = parent::createApplication();
        $config = $app['config'];
        $conexion = $config->get('database.default');
        $base = (string) $config->get("database.connections.{$conexion}.database");

        $segura = ($conexion === 'sqlite' && $base === ':memory:') || str_ends_with($base, '_testing');
        if (! $segura) {
            throw new RuntimeException("Tests abortados: la base '{$base}' ({$conexion}) no es de pruebas (*_testing).");
        }

        return $app;
    }
}
