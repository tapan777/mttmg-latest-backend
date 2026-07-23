<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_password_request_log', function (Blueprint $table) {
            $table->id();
            $table->string('req_type', 50)->default('reset');
            $table->unsignedBigInteger('user_id');
            $table->string('email');
            $table->text('token');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_password_request_log');
    }
};
