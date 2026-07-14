<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('parent_external_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->string('locale', 16)->default('en-us');
            $table->integer('position')->default(0);
            $table->string('source_url')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'position']);
            $table->index('parent_external_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};
