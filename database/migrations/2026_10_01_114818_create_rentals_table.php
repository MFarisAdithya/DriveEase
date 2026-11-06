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
        Schema::create('rentals', function (Blueprint $table) {
            $table->id();
            $table->string('rental_code', 20)->unique(); // Kode booking unik
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // Customer
            $table->foreignId('car_id')->constrained('cars')->onDelete('restrict');

            $table->date('start_date');
            $table->date('end_date');
            $table->integer('total_days');
            $table->decimal('daily_price', 12, 2);
            $table->decimal('total_amount', 12, 2);

            // Validasi Pembayaran oleh Admin
            $table->string('payment_proof')->nullable(); // Foto bukti transfer
            $table->enum('payment_status', ['unpaid', 'pending_approval', 'paid', 'rejected'])->default('unpaid');
            $table->foreignId('payment_verified_by')->nullable()->constrained('users')->nullOnDelete(); // Admin
            $table->timestamp('payment_verified_at')->nullable();

            // Status Operasional Rental
            $table->enum('rental_status', ['pending', 'confirmed', 'picked_up', 'returned', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rentals');
    }
};
