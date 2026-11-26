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
        // Nama di Connection sudah diproses Laravel, termasuk override dari DB_URL.
        // Periksa sebelum test bisa membuka transaksi atau menjalankan migration.
        if ($app['config']->get('database.default') !== 'pgsql'
            || $app['db']->connection('pgsql')->getDatabaseName() !== 'driveease_test') {
            throw new RuntimeException('Pengujian hanya boleh memakai PostgreSQL driveease_test.');
        }
        Storage::fake('local');
        Storage::fake('public');

        return $app;
    }
}
