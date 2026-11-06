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
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_type_id')->constrained('car_types')->onDelete('restrict');
            $table->string('name', 100);                  // Contoh: Toyota Avanza Veloz
            $table->string('plate_number', 15)->unique(); // Contoh: B 1234 ABC
            $table->string('brand', 50);                  // Contoh: Toyota
            $table->year('model_year');
            $table->enum('transmission', ['manual', 'automatic']);
            $table->integer('capacity');                  // Jumlah penumpang
            $table->decimal('price_per_day', 12, 2);      // Tarif sewa per hari
            $table->enum('status', ['available', 'booked', 'rented', 'maintenance'])->default('available');
            $table->string('image')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
