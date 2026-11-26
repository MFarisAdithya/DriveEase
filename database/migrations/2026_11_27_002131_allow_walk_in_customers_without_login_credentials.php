<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // change() mengubah kolom yang sudah ada. PostgreSQL tetap menjaga email
        // yang terisi agar unik, tetapi mengizinkan beberapa pelanggan tanpa email login.
        // Nilai akun lama dan relasi rentals.user_id tidak perlu dipindahkan.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // PostgreSQL menolak NOT NULL jika sudah ada identitas tanpa kredensial.
        // Tidak ada baris yang dihapus dan tidak ada kredensial pengganti yang dibuat.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable(false)->change();
            $table->string('password')->nullable(false)->change();
        });
    }
};
