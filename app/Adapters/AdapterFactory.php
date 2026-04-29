<?php

declare(strict_types=1);

namespace App\Adapters;

use App\Adapters\Contracts\AdapterFactoryInterface;
use InvalidArgumentException;

final class AdapterFactory implements AdapterFactoryInterface
{
    /**
     * @param  array<string, class-string<BlogSourceAdapter>>  $adapters  карта slug → класс адаптера
     */
    public function __construct(
        private readonly array $adapters,
    ) {}

    /**
     * Возвращает адаптер для указанного slug ресурса.
     *
     * @throws InvalidArgumentException если адаптер для slug не зарегистрирован
     */
    public function make(string $slug): BlogSourceAdapter
    {
        if (! isset($this->adapters[$slug])) {
            throw new InvalidArgumentException(
                "Адаптер для ресурса с slug [{$slug}] не зарегистрирован.",
            );
        }

        /** @var BlogSourceAdapter */
        return app($this->adapters[$slug]);
    }
}
