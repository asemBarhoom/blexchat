<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The automation templates of the app's picker (W19), and the starting answers the wizard (W11+)
     * opens with for each trigger when no template is named.
     */
    public function up(): void
    {
        Schema::create('automation_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique()->comment('Stable id the frontend links to, e.g. t1');
            $table->boolean('is_starter')->default(false)->comment('The wizard’s default for its trigger, not on the shelf');
            $table->json('name');
            $table->json('line')->nullable();
            $table->string('build')->comment('quick | flow');
            $table->string('goal')->nullable()->comment('sell | grow | support | book');
            $table->string('trigger')->comment('comment | story | dm | ad');
            $table->boolean('locked')->default(false)->comment('Needs a paid plan');
            $table->string('match')->nullable()->comment('words | any; null: nothing for the wizard to fill');
            $table->json('keywords')->nullable();
            $table->json('public_reply')->nullable();
            $table->json('dm_text')->nullable();
            $table->json('dm_button')->nullable();
            $table->string('link_url', 2048)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_templates');
    }
};
