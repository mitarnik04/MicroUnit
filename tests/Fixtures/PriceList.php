<?php

namespace MicroUnit\Tests\Fixtures;

/** A small concrete class for mocking tests: methods to mock, real behaviour, a protected method. */
class PriceList
{
    public function price(string $product, string $currency = 'EUR'): int
    {
        return 100;
    }

    public function formatted(string $product): string
    {
        return $this->price($product) . ' EUR';
    }

    public function gross(string $product): int
    {
        return intdiv($this->price($product) * (100 + $this->taxPercent()), 100);
    }

    protected function taxPercent(): int
    {
        return 20;
    }
}
