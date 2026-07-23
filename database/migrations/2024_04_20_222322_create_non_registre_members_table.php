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
    // "name" => "request",
    // "membership_number" => "12345678",
    // "email" => "pandu@gmail.com",
    // "discount_type" => "%",
    // "payble_amount" => 1000,
    // "payble_amount" => 800,
    // "payment_date" => "2024-04-28"
    {
        Schema::create('non_registre_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->string('email');
            $table->unsignedBigInteger('offer_package_id');
            $table->string('membership_number');
            $table->string('offer');
            $table->string('payble_amount');
            $table->string('paying_amount');
            $table->string('due');
            $table->string('payment_date');
            $table->timestamps();

            $table->foreign('offer_package_id')
                ->on('offer_packages')
                ->references('id')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('non_registre_members');
    }
};
