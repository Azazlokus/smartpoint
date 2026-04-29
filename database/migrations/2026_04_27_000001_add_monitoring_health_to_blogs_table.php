<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Добавляет поля для отслеживания «здоровья» мониторинга блога:
 *
 *  last_monitored_at   — когда последний раз мониторинг прошёл успешно
 *  monitoring_failures — сколько раз подряд мониторинг завершился неудачей
 *
 * Эти поля позволяют быстро определить деградировавшие блоги
 * без парсинга monitoring_logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blogs', function (Blueprint $table): void {
            $table->timestamp('last_monitored_at')->nullable()->after('next_check_at');
            $table->unsignedTinyInteger('monitoring_failures')->default(0)->after('last_monitored_at');
        });
    }

    public function down(): void
    {
        Schema::table('blogs', function (Blueprint $table): void {
            $table->dropColumn(['last_monitored_at', 'monitoring_failures']);
        });
    }
};
