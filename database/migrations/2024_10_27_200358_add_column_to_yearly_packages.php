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
        Schema::table('yearly_packages', function (Blueprint $table) {
            $table->unsignedBigInteger('admission_value_id');
            $table->foreign('admission_value_id')->on('admission_values')->references('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('yearly_packages', function (Blueprint $table) {
            $table->dropColumn('admission_value_id');
        });
    }
};
