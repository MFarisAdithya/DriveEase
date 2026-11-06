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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'ktp_number')) {
                $table->string('ktp_number', 30)->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'verified_by')) {
                $table->foreignId('verified_by')->nullable()->after('verified_at')->constrained('users')->nullOnDelete();
            }
        });

        // Sync existing nik_ktp to ktp_number if needed
        if (Schema::hasColumn('users', 'nik_ktp') && Schema::hasColumn('users', 'ktp_number')) {
            DB::statement('UPDATE users SET ktp_number = nik_ktp WHERE ktp_number IS NULL AND nik_ktp IS NOT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'verified_by')) {
                $table->dropForeign(['verified_by']);
                $table->dropColumn('verified_by');
            }
            if (Schema::hasColumn('users', 'ktp_number')) {
                $table->dropColumn('ktp_number');
            }
        });
    }
};
