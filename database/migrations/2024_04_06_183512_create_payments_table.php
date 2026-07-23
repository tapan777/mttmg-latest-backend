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
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id');
            $table->unsignedBigInteger('package_id');
            $table->integer('bill_no')->nullable();
            $table->string('offer')->nullable();
            $table->double('payble_amount');
            $table->double('total_payble_amount');
            $table->string('mode_of_payment');
            $table->double('paying_amount');
            $table->double('due');
            $table->integer('payment_type');
            $table->date('date_of_payment');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->integer('package_status')->default(1)->comment('O=> Inactive , 1=> Active');
            $table->string('remarks')->nullable();
            $table->timestamps();

            $table->foreign('member_id')
                ->references('id')
                ->on('members')->onDelete('cascade');
            $table->foreign('package_id')
                ->references('id')
                ->on('packages');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
