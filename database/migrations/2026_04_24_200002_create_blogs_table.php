<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('resource_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('name');
            $table->float('rating', 3, 1)->default(0.0);
            $table->string('cat_name')->nullable();
            $table->string('author')->nullable();
            $table->unsignedTinyInteger('monitor_frequency_hours')->default(4);
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamps();

            $table->unique(['resource_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};
