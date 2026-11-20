<?php

namespace MicroUnit\Benchmarks;

/** Descriptive statistics of a series of measurements. */
final readonly class Statistics
{
    /** @param list<float> $values */
    private function __construct(
        public int $count,
        public float $median,
        public float $mean,
        public float $standardDeviation,
        public float $minimum,
        public float $maximum,
        public float $p90,
    ) {}

    /** @param list<float> $values */
    public static function of(array $values): self
    {
        if ($values === []) {
            throw new \InvalidArgumentException('No measurements.');
        }
        sort($values);
        $count = count($values);
        $mean = array_sum($values) / $count;
        $variance = $count > 1 ? array_sum(array_map(static fn(float $value): float => ($value - $mean) ** 2, $values)) / ($count - 1) : 0.0;

        return new self(
            $count,
            $count % 2 === 1 ? $values[intdiv($count, 2)] : ($values[$count / 2 - 1] + $values[$count / 2]) / 2,
            $mean,
            sqrt($variance),
            $values[0],
            $values[$count - 1],
            $values[(int) ceil(0.9 * $count) - 1], // nearest-rank
        );
    }

    /** Relative spread: standard deviation / mean. */
    public function coefficientOfVariation(): float
    {
        return $this->mean > 0 ? $this->standardDeviation / $this->mean : 0.0;
    }

    /** @return array<string, float|int> */
    public function toArray(): array
    {
        return [
            'runs' => $this->count,
            'median' => round($this->median, 4),
            'mean' => round($this->mean, 4),
            'standardDeviation' => round($this->standardDeviation, 4),
            'coefficientOfVariation' => round($this->coefficientOfVariation(), 4),
            'minimum' => round($this->minimum, 4),
            'p90' => round($this->p90, 4),
            'maximum' => round($this->maximum, 4),
        ];
    }
}
