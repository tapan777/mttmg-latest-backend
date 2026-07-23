<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbl_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Template key used in Text2India API');
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('variables_count')->default(0);
            $table->json('variable_labels')->nullable()->comment('Labels for each {{n}} variable');
            $table->string('used_in')->nullable()->comment('Where this template is triggered');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbl_whatsapp_templates');
    }
};
