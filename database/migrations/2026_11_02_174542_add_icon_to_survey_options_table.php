<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('survey_options', function (Blueprint $table): void {
            $table->string('icon')->nullable()->after('note')->comment('simple-icons slug, like instagram');
            $table->string('icon_image')->nullable()->after('icon')->comment('Uploaded logo on the public disk; shown instead of the slug');
            $table->json('icon_box')->nullable()->after('icon_image')->comment('Crop for a wordmark-shaped logo: viewBox, width, height');
        });
    }

    public function down(): void
    {
        Schema::table('survey_options', function (Blueprint $table): void {
            $table->dropColumn(['icon', 'icon_image', 'icon_box']);
        });
    }
};
