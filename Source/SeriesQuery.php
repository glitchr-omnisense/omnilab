<?php

namespace Omnilab\Source;

use Omnilab\Model\BBox;
use Omnilab\Model\Identifier;
use Omnilab\Model\Point;
use Omnilab\Model\Scheme;

/**
 * What to ask series() for: the variables, in the source's names
 * ("temperature_2m", "precipitation"); the place - a Point, a BBox
 * (Capability::AREA) or a station's Identifier (Capability::STATION: its
 * own records, not the nearest one's); the period, both ends
 * included (a date without a time stands for the whole day); the step
 * (SeriesQuery::HOURLY, DAILY, or another ISO 8601 duration the source
 * knows); the aggregation of a step longer than the measure (Series::MAX,
 * MIN, MEAN, SUM); the time zone the times and the days are given in
 * (null: the source's default, which its README states).
 *
 *   new SeriesQuery(['temperature_2m'], new Point(48.5734, 7.7521), new \DateTimeImmutable('tomorrow'), new \DateTimeImmutable('tomorrow'))
 *   new SeriesQuery(['temperature_2m'], $strasbourg, $from, $to, SeriesQuery::DAILY, Series::MAX)
 *   new SeriesQuery(['TMAX'], Identifier::of(Scheme::GHCN, 'USW00094728'), $from, $to, SeriesQuery::DAILY)
 */
final readonly class SeriesQuery
{
    public const HOURLY = 'PT1H';
    public const DAILY = 'P1D';

    /** The schemes of a station. */
    public const STATIONS = [Scheme::GHCN, Scheme::WIGOS, Scheme::NWS_STATION, Scheme::USGS_SITE, Scheme::COOPS_STATION, Scheme::DWD_STATION];

    /** @param list<string> $variables */
    public function __construct(
        public array $variables,
        public Point|BBox|Identifier $place,
        public ?\DateTimeInterface $from = null,
        public ?\DateTimeInterface $to = null,
        public string $step = self::HOURLY,
        public ?string $aggregation = null,
        public ?string $timezone = null,
    ) {
        if ($place instanceof Identifier && !\in_array($place->scheme, self::STATIONS, true)) {
            throw new \InvalidArgumentException(\sprintf('A series is asked at a place or a station, not at %s.', $place));
        }
        if ([] === $variables) {
            throw new \InvalidArgumentException('A series query names at least one variable.');
        }
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException(\sprintf('The period ends (%s) before it starts (%s).', $to->format('Y-m-d'), $from->format('Y-m-d')));
        }
    }

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
