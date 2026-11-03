<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A /solutions/{slug} page. A page that has a homepage use-case card takes its slug, name, shape and
     * tone from that card (use_case_id); a page without one (education, support) keeps its own here.
     * The sample business is the account the page's conversations run on: an uploaded avatar, or its
     * initial drawn on avatar_color.
     */
    public function up(): void
    {
        Schema::create('solutions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('use_case_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('slug')->nullable()->unique()->comment('Only without a use case');
            $table->json('name')->nullable()->comment('Only without a use case');
            $table->string('shape')->nullable()->comment('Only without a use case');
            $table->string('tone')->nullable()->comment('Only without a use case');
            $table->string('sample_handle');
            $table->string('sample_avatar')->nullable();
            $table->string('avatar_color', 7)->default('#0b0b0f');
            $table->string('avatar_text_color', 7)->default('#ffffff');
            $table->json('headline');
            $table->json('supporting');
            $table->json('closing');
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('solutions');
    }
};
