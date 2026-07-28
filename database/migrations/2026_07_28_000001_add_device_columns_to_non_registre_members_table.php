<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('non_registre_members', function (Blueprint $table) {
            if (!Schema::hasColumn('non_registre_members', 'start_date')) {
                $table->string('start_date')->nullable();
            }
            if (!Schema::hasColumn('non_registre_members', 'end_date')) {
                $table->string('end_date')->nullable();
            }
            if (!Schema::hasColumn('non_registre_members', 'card_number')) {
                $table->string('card_number')->nullable();
            }
            if (!Schema::hasColumn('non_registre_members', 'on_device')) {
                $table->boolean('on_device')->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::table('non_registre_members', function (Blueprint $table) {
            foreach (['card_number', 'on_device'] as $column) {
                if (Schema::hasColumn('non_registre_members', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
