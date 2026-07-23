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
        Schema::create('members', function (Blueprint $table) {
            
            $table->id();
            $table->string('membership_number');
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->string('alternate_phone')->nullable();
            $table->string('image');
            $table->string('height');
            $table->string('weight');
            $table->string('sex');
            $table->date('dob');
            $table->string('identification_type');
            $table->string('identification_id')->unique();
            $table->string('occupation')->nullable();
            $table->string('address');
            $table->integer('status');
            $table->date('pause')->nullable();
            $table->date('resume')->nullable();


            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
