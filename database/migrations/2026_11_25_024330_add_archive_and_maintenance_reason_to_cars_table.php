<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            // Kolom nullable menjaga kendaraan lama tetap aktif seusai migrasi.
            $table->timestamp('archived_at')->nullable();
            $table->text('maintenance_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('cars', function (Blueprint $table): void {
            $table->dropColumn(['archived_at', 'maintenance_reason']);
        });
    }
};
