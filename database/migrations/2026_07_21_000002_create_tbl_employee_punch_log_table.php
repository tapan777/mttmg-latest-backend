<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_employee_punch_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');
            $table->date('punch_date');
            $table->time('punch_time');
            $table->enum('punch_type', ['in', 'out']);
            $table->string('source')->default('device')->comment('device | manual');
            $table->string('device_sn')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'punch_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_employee_punch_log');
    }
};
