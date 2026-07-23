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
        Schema::table('trainer_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->after('trainer_package_id')->nullable();

            // Add the foreign key constraint
            $table->foreign('employee_id')->references('id')->on('employees')
                ->onDelete('cascade'); // Adjust onDelete action as needed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trainer_payments', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropColumn('employee_id');
        });
    }
};
