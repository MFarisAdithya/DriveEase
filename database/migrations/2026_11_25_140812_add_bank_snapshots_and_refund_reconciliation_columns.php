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
        // Simpan snapshot rekening pembayaran saat perpanjangan disetujui agar tetap konsisten jika rekening diubah.
        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->string('payment_bank_name')->nullable()->after('payment_deadline_at');
            $table->string('payment_account_number', 50)->nullable()->after('payment_bank_name');
            $table->string('payment_account_holder')->nullable()->after('payment_account_number');
        });

        // Simpan snapshot rekening tagihan tambahan, nominal kelebihan bayar (overpayment),
        // dan nominal total refund yang sudah benar-benar ditransfer agar rekonsiliasi keuangan akurat.
        Schema::table('rentals', function (Blueprint $table): void {
            $table->string('additional_charge_bank_name')->nullable()->after('additional_charges_assessed_by');
            $table->string('additional_charge_account_number', 50)->nullable()->after('additional_charge_bank_name');
            $table->string('additional_charge_account_holder')->nullable()->after('additional_charge_account_number');
            $table->decimal('overpayment_amount', 12, 2)->default(0)->after('paid_amount');
            $table->decimal('refund_realized_amount', 12, 2)->default(0)->after('refund_amount');
        });

        DB::table('rentals')
            ->where('refund_status', 'processed')
            ->update(['refund_realized_amount' => DB::raw('refund_amount')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropColumn([
                'additional_charge_bank_name',
                'additional_charge_account_number',
                'additional_charge_account_holder',
                'overpayment_amount',
                'refund_realized_amount',
            ]);
        });

        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->dropColumn([
                'payment_bank_name',
                'payment_account_number',
                'payment_account_holder',
            ]);
        });
    }
};
