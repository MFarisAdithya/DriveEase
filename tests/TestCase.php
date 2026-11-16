<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        if ($app['config']->get('database.default') !== 'pgsql' || $app['config']->get('database.connections.pgsql.database') !== 'driveease_test') {
            throw new RuntimeException('Pengujian hanya boleh memakai PostgreSQL driveease_test.');
        }
        Storage::fake('local');
        Storage::fake('public');

        return $app;
    }
}
