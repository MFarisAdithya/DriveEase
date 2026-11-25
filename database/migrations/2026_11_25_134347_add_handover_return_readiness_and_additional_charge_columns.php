<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tambah kolom pelacakan kesiapan unit kendaraan secara terpisah dari status pengembalian (S-05, SYS-12).
        Schema::table('cars', function (Blueprint $table): void {
            $table->string('readiness_status', 30)->default('ready')->after('maintenance_reason');
            $table->text('readiness_notes')->nullable()->after('readiness_status');
            $table->foreignId('readiness_checked_by')->nullable()->after('readiness_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('readiness_checked_at')->nullable()->after('readiness_checked_by');
        });

        DB::table('cars')->where('status', 'maintenance')->update(['readiness_status' => 'needs_maintenance']);
        DB::table('cars')->where('status', 'rented')->update(['readiness_status' => 'in_use']);

        // Tambah pelacakan pemeriksaan identitas saat serah-terima, penanda kerusakan, dan unique index anti serah-terima ganda.
        Schema::table('inspections', function (Blueprint $table): void {
            $table->boolean('identity_checked')->default(false)->after('inspection_type');
            $table->boolean('has_damage')->default(false)->after('condition_photo');
            $table->text('damage_description')->nullable()->after('has_damage');
            $table->unique(['rental_id', 'inspection_type'], 'inspections_rental_type_unique');
        });

        // Tambah kolom pengembalian aktual, denda keterlambatan, biaya BBM/kerusakan, pelunasan tagihan tambahan, dan penyelesaian transaksi.
        Schema::table('rentals', function (Blueprint $table): void {
            $table->timestamp('actual_return_at')->nullable()->after('actual_pickup_at');
            $table->integer('late_minutes')->default(0)->after('actual_return_at');
            $table->decimal('late_fee_amount', 12, 2)->default(0)->after('late_minutes');
            $table->decimal('fuel_fee_amount', 12, 2)->default(0)->after('late_fee_amount');
            $table->text('fuel_fee_reason')->nullable()->after('fuel_fee_amount');
            $table->decimal('damage_fee_amount', 12, 2)->default(0)->after('fuel_fee_reason');
            $table->text('damage_fee_reason')->nullable()->after('damage_fee_amount');
            $table->string('damage_evidence_path')->nullable()->after('damage_fee_reason');
            $table->decimal('additional_charges_total', 12, 2)->default(0)->after('damage_evidence_path');
            $table->timestamp('additional_charges_assessed_at')->nullable()->after('additional_charges_total');
            $table->foreignId('additional_charges_assessed_by')->nullable()->after('additional_charges_assessed_at')->constrained('users')->nullOnDelete();
            $table->string('additional_charge_status', 30)->default('none')->after('additional_charges_assessed_by');
            $table->decimal('additional_charge_paid_amount', 12, 2)->default(0)->after('additional_charge_status');
            $table->string('additional_charge_proof')->nullable()->after('additional_charge_paid_amount');
            $table->timestamp('additional_charge_submitted_at')->nullable()->after('additional_charge_proof');
            $table->foreignId('additional_charge_verified_by')->nullable()->after('additional_charge_submitted_at')->constrained('users')->nullOnDelete();
            $table->timestamp('additional_charge_verified_at')->nullable()->after('additional_charge_verified_by');
            $table->string('additional_charge_payment_reference', 100)->nullable()->after('additional_charge_verified_at');
            $table->text('additional_charge_rejection_reason')->nullable()->after('additional_charge_payment_reference');
            $table->timestamp('completed_at')->nullable()->after('additional_charge_rejection_reason');
            $table->foreignId('completed_by')->nullable()->after('completed_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropForeign(['additional_charges_assessed_by']);
            $table->dropForeign(['additional_charge_verified_by']);
            $table->dropForeign(['completed_by']);
            $table->dropColumn([
                'actual_return_at',
                'late_minutes',
                'late_fee_amount',
                'fuel_fee_amount',
                'fuel_fee_reason',
                'damage_fee_amount',
                'damage_fee_reason',
                'damage_evidence_path',
                'additional_charges_total',
                'additional_charges_assessed_at',
                'additional_charges_assessed_by',
                'additional_charge_status',
                'additional_charge_paid_amount',
                'additional_charge_proof',
                'additional_charge_submitted_at',
                'additional_charge_verified_by',
                'additional_charge_verified_at',
                'additional_charge_payment_reference',
                'additional_charge_rejection_reason',
                'completed_at',
                'completed_by',
            ]);
        });

        Schema::table('inspections', function (Blueprint $table): void {
            $table->dropUnique('inspections_rental_type_unique');
            $table->dropColumn(['identity_checked', 'has_damage', 'damage_description']);
        });

        Schema::table('cars', function (Blueprint $table): void {
            $table->dropForeign(['readiness_checked_by']);
            $table->dropColumn([
                'readiness_status',
                'readiness_notes',
                'readiness_checked_by',
                'readiness_checked_at',
            ]);
        });
    }
};
