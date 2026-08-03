<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_command_logs', function (Blueprint $table) {
            $table->id();
            $table->string('sn');
            $table->unsignedBigInteger('cmd_id');
            $table->string('action'); // add_or_update_user | delete_user | clear_attendance | reboot | unknown
            $table->string('pin')->nullable();
            $table->string('card_number')->nullable();
            $table->text('command');
            $table->string('status')->default('queued'); // queued | dispatched | success | failed
            $table->text('error_message')->nullable();
            $table->dateTime('dispatched_at')->nullable();
            $table->dateTime('acked_at')->nullable();
            $table->timestamps();

            $table->index(['sn', 'cmd_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_command_logs');
    }
};
