<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('locale', 16)->default('en-us');
            $table->integer('position')->default(0);
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
