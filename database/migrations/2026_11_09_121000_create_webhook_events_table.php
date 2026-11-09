<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void { Schema::create('webhook_events', function (Blueprint $table): void { $table->id(); $table->string('provider'); $table->string('event_key')->unique(); $table->string('status'); $table->string('object')->nullable(); $table->json('payload')->nullable(); $table->text('error')->nullable(); $table->timestamps(); $table->index(['provider', 'status']); }); }
    public function down(): void { Schema::dropIfExists('webhook_events'); }
};
