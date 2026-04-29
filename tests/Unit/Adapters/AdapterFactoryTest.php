<?php

declare(strict_types=1);

namespace Tests\Unit\Adapters;

use App\Adapters\AdapterFactory;
use App\Adapters\BlogSourceAdapter;
use App\Adapters\MockAdapter;
use InvalidArgumentException;
use Tests\TestCase;

final class AdapterFactoryTest extends TestCase
{
    private AdapterFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();
        /** @var array<string, class-string<\App\Adapters\BlogSourceAdapter>> $adapters */
        $adapters = config('monitoring.adapters');
        $this->factory = new AdapterFactory($adapters);
    }

    public function test_resolves_mock_adapter_by_slug(): void
    {
        $adapter = $this->factory->make('mock');

        $this->assertInstanceOf(MockAdapter::class, $adapter);
        $this->assertInstanceOf(BlogSourceAdapter::class, $adapter);
    }

    public function test_throws_for_unknown_slug(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('wordpress');

        $this->factory->make('wordpress');
    }
}
