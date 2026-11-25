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
        // Schema::table() menambahkan kolom rekonsiliasi dana masuk dan pengembalian dana
        // pada rental_extensions agar dana perpanjangan yang batal/bentrok dapat direfund penuh.
        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->decimal('paid_amount', 12, 2)->default(0)->after('additional_amount');
            $table->decimal('verified_amount', 12, 2)->nullable()->after('paid_amount');
            $table->decimal('refund_amount', 12, 2)->default(0)->after('payment_rejection_reason');
            $table->string('refund_status', 20)->default('none')->after('refund_amount');
            $table->timestamp('refund_transferred_at')->nullable()->after('refund_status');
            $table->string('refund_reference', 100)->nullable()->after('refund_transferred_at');
            $table->text('refund_notes')->nullable()->after('refund_reference');
            $table->foreignId('refund_processed_by')->nullable()->after('refund_notes')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rental_extensions', function (Blueprint $table): void {
            $table->dropForeign(['refund_processed_by']);
            $table->dropColumn([
                'paid_amount',
                'verified_amount',
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
