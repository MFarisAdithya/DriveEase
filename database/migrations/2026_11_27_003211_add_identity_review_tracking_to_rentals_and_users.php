<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('identity_submitted_at')->nullable();
            $table->timestamp('identity_rejected_at')->nullable();
        });

        Schema::table('rentals', function (Blueprint $table): void {
            // Null mempertahankan perilaku pesanan yang dibuat sebelum revisi UAT.
            // Nilai pada pesanan baru diperbarui bersama transaksi verifikasi.
            $table->string('identity_status', 16)->nullable();
            $table->timestamp('identity_deadline_at')->nullable();
            $table->timestamp('review_followup_at')->nullable();
            $table->boolean('payment_review_blocked')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table): void {
            $table->dropColumn(['identity_status', 'identity_deadline_at', 'review_followup_at', 'payment_review_blocked']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['identity_submitted_at', 'identity_rejected_at']);
        });
    }
};
