<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = ['ktp_number', 'nik_ktp', 'sim_number'];

    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach (self::COLUMNS as $column) {
                $table->text($column)->nullable()->change();
            }
        });

        $this->convertNumbers(encrypt: true);
    }

    public function down(): void
    {
        $this->convertNumbers(encrypt: false);

        Schema::table('users', function (Blueprint $table): void {
            $table->string('ktp_number', 30)->nullable()->change();
            $table->string('nik_ktp', 25)->nullable()->change();
            $table->string('sim_number', 30)->nullable()->change();
        });
    }

    private function convertNumbers(bool $encrypt): void
    {
        // chunkById() membaca baris sedikit demi sedikit. Query builder mendapat
        // nilai mentah agar setiap nomor hanya diproses sekali saat migrasi.
        DB::table('users')->select('id', ...self::COLUMNS)->chunkById(200, function ($users) use ($encrypt): void {
            foreach ($users as $user) {
                $numbers = [];
                foreach (self::COLUMNS as $column) {
                    if ($user->$column !== null) {
                        $numbers[$column] = $encrypt
                            ? Crypt::encryptString($user->$column)
                            : Crypt::decryptString($user->$column);
                    }
                }

                if ($numbers !== []) {
                    DB::table('users')->where('id', $user->id)->update($numbers);
                }
            }
        });
    }
};
