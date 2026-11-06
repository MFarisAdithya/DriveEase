<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->foreignId('inspector_id')->constrained('users')->onDelete('restrict'); // Petugas Lapangan

            $table->enum('inspection_type', ['pickup', 'return']);
            $table->integer('odometer_km');                               // Catatan kilometer
            $table->enum('fuel_level', ['empty', 'quarter', 'half', 'three_quarters', 'full']);
            $table->text('condition_notes')->nullable();                  // Catatan lecet / kerusakan
            $table->string('condition_photo')->nullable();                // Foto bukti fisik mobil
            $table->decimal('penalty_fee', 12, 2)->default(0);            // Denda (bila ada)
            $table->text('penalty_reason')->nullable();
            $table->timestamp('inspected_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
