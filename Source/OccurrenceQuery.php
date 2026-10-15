<?php

namespace Omnilab\Source;

use Omnilab\Model\BBox;
use Omnilab\Model\Point;

/**
 * What to ask occurrences() for: the things found or happened at a place
 * in a period - the observations of a species, the earthquakes of a
 * region. The place is a BBox, or a Point and the radius around it in
 * kilometres (null: the source's default, which its README states); no
 * place is anywhere. The period has both ends included, a date without a
 * time standing for its whole day; no end is open.
 *
 * What only one domain filters on - a taxon, a country, a magnitude - is a
 * filter, by key: the keys below are shared by the sources that read them
 * (a new source reuses one before it makes one), and a source refuses a
 * key it does not read, before any call, rather than answer unfiltered.
 * Each source's README lists its keys and the values they take.
 *
 *   new OccurrenceQuery(new Point(-24.99, 31.59), new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31'), [OccurrenceQuery::TAXON => '4CGXP'], radius: 100)
 *   new OccurrenceQuery(new BBox(-25, 35, 45, 72), $from, $to, [OccurrenceQuery::MIN_MAGNITUDE => 4], sort: 'magnitude')
 *   $query->with(['cursor' => $page->next])
 */
final readonly class OccurrenceQuery
{
    /** The latest first. */
    public const NEWEST = 'newest';
    /** The earliest first. */
    public const OLDEST = 'oldest';

    /** A taxon: an Identifier, a key or a scientific name the source reads (GBIF). */
    public const TAXON = 'taxon';
    /** A country, ISO 3166-1 alpha-2 ("ZA"), or a list of them (GBIF). */
    public const COUNTRY = 'country';
    /** The source's word for what it is, or a list of them: "earthquake", "quarry blast" (USGS); "human_observation", "preserved_specimen" (GBIF's basis of record). */
    public const KIND = 'kind';
    /** The smallest magnitude, included (USGS). */
    public const MIN_MAGNITUDE = 'min_magnitude';
    /** The largest magnitude, included (USGS). */
    public const MAX_MAGNITUDE = 'max_magnitude';

    /**
     * @param array<string, mixed> $filters by key: OccurrenceQuery::TAXON, COUNTRY, KIND... or a key of the source's own
     * @param float|null           $radius  around a Point, in kilometres; null: the source's default
     * @param int                  $limit   occurrences per page (each source caps it)
     * @param string|null          $cursor  the Page::$next of the page before
     * @param string|null          $sort    OccurrenceQuery::NEWEST, OLDEST, or a key the source sorts by ("magnitude"); null: the source's own order
     */
    public function __construct(
        public Point|BBox|null $place = null,
        public ?\DateTimeInterface $from = null,
        public ?\DateTimeInterface $to = null,
        public array $filters = [],
        public ?float $radius = null,
        public int $limit = 25,
        public ?string $cursor = null,
        public ?string $sort = null,
    ) {
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException(\sprintf('The period ends (%s) before it starts (%s).', $to->format(\DATE_ATOM), $from->format(\DATE_ATOM)));
        }
        if (null !== $radius && !$place instanceof Point) {
            throw new \InvalidArgumentException('A radius is around a Point.');
        }
        if (null !== $radius && $radius <= 0) {
            throw new \InvalidArgumentException(\sprintf('A radius of %s km holds nothing.', $radius));
        }
    }

    /** A filter's value; null when the query does not filter on it. */
    public function filter(string $key): mixed
    {
        return $this->filters[$key] ?? null;
    }

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
