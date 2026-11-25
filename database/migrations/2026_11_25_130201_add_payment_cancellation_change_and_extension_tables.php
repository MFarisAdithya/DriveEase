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
        // Lengkapi tabel rentals dengan kolom pelacakan pelunasan, penolakan bukti,
        // permintaan pembatalan, serta perhitungan dan realisasi pengembalian dana (refund).
        Schema::table('rentals', function (Blueprint $table): void {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('total_amount');
            $table->timestamp('payment_submitted_at')->nullable()->after('payment_verified_at');
            $table->timestamp('payment_rejected_at')->nullable()->after('payment_submitted_at');
            $table->text('payment_rejection_reason')->nullable()->after('payment_rejected_at');
            $table->string('cancellation_type', 30)->nullable()->after('cancellation_reason');
            $table->timestamp('cancellation_requested_at')->nullable()->after('cancellation_type');
            $table->text('cancellation_request_reason')->nullable()->after('cancellation_requested_at');
            $table->unsignedTinyInteger('refund_percentage')->default(0)->after('cancellation_request_reason');
            $table->decimal('refund_amount', 12, 2)->default(0)->after('refund_percentage');
            $table->string('refund_status', 20)->default('none')->after('refund_amount');
            $table->timestamp('refund_transferred_at')->nullable()->after('refund_status');
            $table->string('refund_reference', 100)->nullable()->after('refund_transferred_at');
            $table->text('refund_notes')->nullable()->after('refund_reference');
            $table->foreignId('refund_processed_by')->nullable()->after('refund_notes')->constrained('users')->nullOnDelete();
        });

        // Simpan seluruh riwayat unggahan bukti transfer maupun pelunasan kekurangan nominal
        // secara terpisah agar rekam jejak pemeriksaan admin dan alasan penolakan selalu utuh.
        Schema::create('rental_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('payment_type', 20)->default('booking');
            $table->decimal('claimed_amount', 12, 2);
            $table->decimal('verified_amount', 12, 2)->nullable();
            $table->string('proof_path');
            $table->string('status', 20)->default('pending_approval');
            $table->text('rejection_reason')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });

        // Catat usulan perubahan jadwal maupun penggantian unit mobil antara admin dan pelanggan.
        Schema::create('rental_change_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->string('request_type', 30);
            $table->string('initiated_by_role', 20);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('proposed_car_id')->nullable()->constrained('cars')->nullOnDelete();
            $table->timestamp('proposed_pickup_at')->nullable();
            $table->integer('proposed_total_days')->nullable();
            $table->timestamp('proposed_return_at')->nullable();
            $table->decimal('proposed_total_amount', 12, 2)->nullable();
            $table->boolean('waive_price_difference')->default(false);
            $table->text('reason');
            $table->string('status', 30);
            $table->foreignId('responded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->text('response_notes')->nullable();
            $table->timestamps();
        });

        // Catat pengajuan perpanjangan sewa, persetujuan admin, pembayaran tambahan, dan aktivasi jadwal baru.
        Schema::create('rental_extensions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('additional_days');
            $table->timestamp('previous_return_at');
            $table->timestamp('proposed_return_at');
            $table->decimal('daily_price', 12, 2);
            $table->decimal('additional_amount', 12, 2);
            $table->string('status', 30)->default('pending_approval');
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('payment_proof')->nullable();
            $table->timestamp('payment_submitted_at')->nullable();
            $table->timestamp('payment_deadline_at')->nullable();
            $table->foreignId('payment_verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('payment_verified_at')->nullable();
            $table->text('payment_rejection_reason')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Jejak audit kronologis seluruh aksi penting pada transaksi (pelaku, waktu, alasan, metadata).
        Schema::create('rental_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->onDelete('cascade');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rental_histories');
        Schema::dropIfExists('rental_extensions');
        Schema::dropIfExists('rental_change_requests');
        Schema::dropIfExists('rental_payments');

        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropForeign(['refund_processed_by']);
            $table->dropColumn([
                'paid_amount',
                'payment_submitted_at',
                'payment_rejected_at',
                'payment_rejection_reason',
                'cancellation_type',
                'cancellation_requested_at',
                'cancellation_request_reason',
                'refund_percentage',
                'refund_amount',
                'refund_status',
                'refund_transferred_at',
                'refund_reference',
                'refund_notes',
                'refund_processed_by',
            ]);
        });
    }
};
