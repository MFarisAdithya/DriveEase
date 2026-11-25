<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tambah kolom tanggal aktual penerimaan kas pada pembayaran serta tabel buku kas berbasis kas (A-01, A-12).
     * Data historis yang tidak memiliki tanggal aktual penerimaan sengaja tidak diisi ulang agar tidak mengarang tanggal kas.
     */
    public function up(): void
    {
        Schema::table('rental_payments', function (Blueprint $table): void {
            $table->timestamp('received_at')->nullable()->after('verified_at');
        });

        Schema::create('rental_cash_flows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('rental_id')->constrained('rentals')->cascadeOnDelete();
            $table->foreignId('rental_payment_id')->nullable()->constrained('rental_payments')->nullOnDelete();
            $table->foreignId('rental_extension_id')->nullable()->constrained('rental_extensions')->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('direction', 16);
            $table->string('category', 40);
            $table->decimal('amount', 12, 2);
            $table->timestamp('occurred_at');
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'direction']);
            $table->index(['rental_id', 'occurred_at']);
        });

        // Partial unique index PostgreSQL mencegah satu bukti pembayaran utama dicatat ganda sebagai arus masuk.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX rental_cash_flows_payment_direction_unique ON rental_cash_flows (rental_payment_id, direction) WHERE rental_payment_id IS NOT NULL');
        }
    }

    /**
     * Balikkan tabel buku arus kas dan kolom received_at saat rollback.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS rental_cash_flows_payment_direction_unique');
        }

        Schema::dropIfExists('rental_cash_flows');

        Schema::table('rental_payments', function (Blueprint $table): void {
            $table->dropColumn('received_at');
        });
    }
};
