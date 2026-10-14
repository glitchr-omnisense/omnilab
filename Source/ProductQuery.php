<?php

namespace Omnilab\Source;

use Omnilab\Model\BBox;
use Omnilab\Model\Point;

/**
 * What to ask products() for. Every criterion is optional; a source applies
 * those it understands and refuses $maxCloudCover without
 * Capability::CLOUD_COVER, and a BBox without Capability::AREA, rather than
 * answer unfiltered.
 *
 *   new ProductQuery(['sentinel-2-l2a'], new Point(48.5734, 7.7521), new \DateTimeImmutable('-10 days'), maxCloudCover: 30)
 *   $query->with(['cursor' => $page->next])
 */
final readonly class ProductQuery
{
    public const NEWEST = 'newest';
    public const OLDEST = 'oldest';

    /**
     * @param list<string> $collections   the source's collection ids ("sentinel-2-l2a"); empty: all
     * @param float|null   $maxCloudCover in percent, included
     * @param int          $limit         products per page (each source caps it)
     * @param string|null  $cursor        the Page::$next of the page before
     */
    public function __construct(
        public array $collections = [],
        public Point|BBox|null $place = null,
        public ?\DateTimeInterface $from = null,
        public ?\DateTimeInterface $to = null,
        public ?float $maxCloudCover = null,
        public int $limit = 25,
        public ?string $cursor = null,
        public string $sort = self::NEWEST,
    ) {
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException(\sprintf('The period ends (%s) before it starts (%s).', $to->format(\DATE_ATOM), $from->format(\DATE_ATOM)));
        }
    }

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }
}
