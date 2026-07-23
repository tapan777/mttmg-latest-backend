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
        Schema::create('trainer_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('trainer_package_id');
            $table->string('offer')->nullable();
            $table->double('payble_amount');
            $table->double('total_payble_amount');
            $table->string('mode_of_payment');
            $table->double('paying_amount');
            $table->double('payment_type')->comment("0=trainer package payment, 1= due payment");
            $table->double('due');
            $table->date('date_of_payment');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('package_status');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->foreign('member_id')
                ->references('id')
                ->on('members');
            $table->foreign('trainer_package_id')
                ->references('id')
                ->on('trainer_packages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trainer_payments');
    }
};
