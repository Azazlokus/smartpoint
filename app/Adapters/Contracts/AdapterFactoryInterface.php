<?php

declare(strict_types=1);

namespace App\Adapters\Contracts;

use App\Adapters\BlogSourceAdapter;

interface AdapterFactoryInterface
{
    /**
     * Возвращает адаптер для указанного slug ресурса.
     */
    public function make(string $slug): BlogSourceAdapter;
}
