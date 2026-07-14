<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('external_id')->unique();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->longText('body')->nullable();
            $table->text('body_text')->nullable();
            $table->string('locale', 16)->default('en-us');
            $table->integer('position')->default(0);
            $table->boolean('promoted')->default(false);
            $table->unsignedInteger('vote_sum')->default(0);
            $table->string('source_url')->nullable();
            $table->timestamp('remote_created_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamps();

            $table->index(['section_id', 'position']);
            $table->index('promoted');
            $table->index('slug');
        });

        // FULLTEXT search index (works with MySQL/MariaDB; ignored on sqlite)
        if (in_array(config('database.default'), ['mysql', 'mariadb'])) {
            \DB::statement('ALTER TABLE articles ADD FULLTEXT search_index (title, body_text)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('articles');
    }
};
