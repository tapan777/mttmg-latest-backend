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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->unsignedBigInteger('main_package_payment_id')->nullable();
            $table->unsignedBigInteger('trainer_package_payment_id')->nullable();
            $table->unsignedBigInteger('yearly_package_payment_id')->nullable();
            $table->unsignedBigInteger('non_registre_member_id')->nullable();
            $table->timestamps();

            $table->foreign('member_id')
                ->on('members')
                ->references('id')
                ->onDelete('cascade');
            $table->foreign('main_package_payment_id')
                ->on('payments')
                ->references('id')
                ->onDelete('cascade');
            $table->foreign('trainer_package_payment_id')
                ->on('trainer_payments')
                ->references('id')
                ->onDelete('cascade');
            $table->foreign('yearly_package_payment_id')
                ->on('yearly_packages')
                ->references('id')
                ->onDelete('cascade');
            $table->foreign('non_registre_member_id')
                ->on('non_registre_members')
                ->references('id')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
