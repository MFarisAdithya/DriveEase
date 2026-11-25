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
        // Schema::table() memodifikasi tabel rentals yang sudah ada untuk menambahkan
        // jadwal presisi jam (WIB), kanal pemesanan, snapshot rekening, serta jejak pembatalan.
        Schema::table('rentals', function (Blueprint $table): void {
            $table->timestamp('pickup_at')->nullable()->after('end_date');
            $table->timestamp('return_at')->nullable()->after('pickup_at');
            $table->timestamp('actual_pickup_at')->nullable()->after('return_at');
            $table->timestamp('payment_deadline_at')->nullable()->after('actual_pickup_at');
            $table->string('booking_channel', 20)->default('online')->after('payment_deadline_at');
            $table->foreignId('created_by')->nullable()->after('booking_channel')->constrained('users')->nullOnDelete();
            $table->timestamp('terms_accepted_at')->nullable()->after('created_by');
            $table->string('payment_bank_name')->nullable()->after('terms_accepted_at');
            $table->string('payment_account_number', 50)->nullable()->after('payment_bank_name');
            $table->string('payment_account_holder')->nullable()->after('payment_account_number');
            $table->timestamp('cancelled_at')->nullable()->after('payment_account_holder');
            $table->text('cancellation_reason')->nullable()->after('cancelled_at');
            $table->text('status_notice')->nullable()->after('cancellation_reason');
            $table->string('idempotency_key', 64)->nullable()->unique()->after('status_notice');

            $table->index(['car_id', 'rental_status', 'pickup_at', 'return_at'], 'rentals_car_schedule_idx');
        });

        // Pada PostgreSQL, kolom enum Laravel dibuat sebagai CHECK constraint.
        // Blok ini mencari constraint rental_status yang ada lalu menggantinya agar
        // mendukung status 'completed' dan 'expired' sesuai siklus transaksi SKPL.
        if (DB::getDriverName() === 'pgsql') {
            $constraint = DB::selectOne(
                "SELECT conname FROM pg_constraint WHERE conrelid = 'rentals'::regclass AND contype = 'c' AND pg_get_constraintdef(oid) LIKE '%rental_status%'"
            );
            if ($constraint !== null && isset($constraint->conname)) {
                DB::statement('ALTER TABLE rentals DROP CONSTRAINT "'.$constraint->conname.'"');
            }

            DB::statement(
                "ALTER TABLE rentals ADD CONSTRAINT rentals_rental_status_check CHECK (rental_status IN ('pending', 'confirmed', 'picked_up', 'returned', 'completed', 'cancelled', 'expired'))"
            );

            // Backfill baris historis yang sebelumnya hanya mengisi start_date dan end_date.
            DB::statement('UPDATE rentals SET pickup_at = COALESCE(pickup_at, start_date::timestamp), return_at = COALESCE(return_at, end_date::timestamp)');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE rentals DROP CONSTRAINT IF EXISTS rentals_rental_status_check');
            DB::statement(
                "ALTER TABLE rentals ADD CONSTRAINT rentals_rental_status_check CHECK (rental_status IN ('pending', 'confirmed', 'picked_up', 'returned', 'cancelled'))"
            );
        }

        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropIndex('rentals_car_schedule_idx');
            $table->dropForeign(['created_by']);
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn([
                'pickup_at',
                'return_at',
                'actual_pickup_at',
                'payment_deadline_at',
                'booking_channel',
                'created_by',
                'terms_accepted_at',
                'payment_bank_name',
                'payment_account_number',
                'payment_account_holder',
                'cancelled_at',
                'cancellation_reason',
                'status_notice',
                'idempotency_key',
            ]);
        });
    }
};
