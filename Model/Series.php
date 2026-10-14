<?php

namespace Omnilab\Model;

/**
 * One variable measured, modelled or forecast at a place over a time: the
 * hourly temperature at 2 m in Strasbourg, the daily sum of precipitation,
 * the monthly mean of NO2 over a box. Points are in time order; a value is
 * null where the source has none.
 *
 *   $series->variable;             // "temperature_2m"
 *   $series->unit;                 // "°C", as the source spells it
 *   foreach ($series as [$time, $value]) { ... }
 *   $series->max();                // 20.4
 *
 * $step is an ISO 8601 duration ("PT1H", "PT15M", "P1D"), null when the
 * points are irregular; $aggregation says what a point of a step stands
 * for (Series::MEAN, MAX, MIN, SUM), null for an instant value.
 *
 * @implements \IteratorAggregate<int, array{\DateTimeImmutable, int|float|null}>
 */
final readonly class Series implements \IteratorAggregate, \Countable
{
    public const MEAN = 'mean';
    public const MAX = 'max';
    public const MIN = 'min';
    public const SUM = 'sum';

    /**
     * @param list<array{\DateTimeImmutable, int|float|null}> $points
     * @param bool|null                                       $forecast true when the points are a forecast, false when observed or reanalysed, null when mixed or unknown
     */
    public function __construct(
        public string $variable,
        public ?string $unit,
        public Point|BBox $place,
        public array $points = [],
        public ?string $step = null,
        public ?string $aggregation = null,
        public ?string $label = null,
        public ?bool $forecast = null,
        public ?string $source = null,
    ) {
    }

    /** @return list<int|float|null> */
    public function values(): array
    {
        return array_map(static fn (array $point) => $point[1], $this->points);
    }

    /** @return list<\DateTimeImmutable> */
    public function times(): array
    {
        return array_map(static fn (array $point) => $point[0], $this->points);
    }

    /** The value at that exact time; null when there is no point then, or no value. */
    public function at(\DateTimeInterface $time): int|float|null
    {
        foreach ($this->points as [$at, $value]) {
            if ($at->getTimestamp() === $time->getTimestamp()) {
                return $value;
            }
        }

        return null;
    }

    public function min(): int|float|null
    {
        $values = array_filter($this->values(), static fn ($v) => null !== $v);

        return $values ? min($values) : null;
    }

    public function max(): int|float|null
    {
        $values = array_filter($this->values(), static fn ($v) => null !== $v);

        return $values ? max($values) : null;
    }

    public function mean(): ?float
    {
        $values = array_filter($this->values(), static fn ($v) => null !== $v);

        return $values ? array_sum($values) / \count($values) : null;
    }

    /** The points from $from (included) to $to (excluded). */
    public function between(\DateTimeInterface $from, \DateTimeInterface $to): self
    {
        $points = array_values(array_filter($this->points, static fn (array $p) => $p[0] >= $from && $p[0] < $to));

        return new self($this->variable, $this->unit, $this->place, $points, $this->step, $this->aggregation, $this->label, $this->forecast, $this->source);
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->points);
    }

    public function count(): int
    {
        return \count($this->points);
    }
}
