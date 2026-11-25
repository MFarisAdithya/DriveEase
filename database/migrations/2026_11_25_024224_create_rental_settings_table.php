<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rental_settings', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('business_name');
            $table->text('services');
            $table->text('address');
            $table->string('contact');
            $table->string('operating_hours');
            $table->text('terms');
            $table->string('bank_name');
            $table->string('account_number', 50);
            $table->string('account_holder');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rental_settings');
    }
};
