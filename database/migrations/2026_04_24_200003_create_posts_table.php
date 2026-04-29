<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('blog_id')->constrained()->cascadeOnDelete();
            $table->string('external_id');
            $table->string('title');
            $table->text('body');
            $table->float('rating', 3, 1)->default(0.0);
            $table->json('reactions')->nullable();
            $table->timestamps();

            $table->unique(['blog_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
