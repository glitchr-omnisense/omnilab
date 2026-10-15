<?php

namespace Omnilab\Source;

use Omnilab\Model\Asset;
use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Model\Product;

/**
 * The reading of a STAC API (stacspec.org, 1.0) that the catalogues share:
 * a ProductQuery written as an item search's parameters, its answer read
 * back into Products, a collection read as a dataset. A source makes the
 * call itself (its getJson(), its throttle, its errors) and gives the
 * answer here.
 *
 *   $data = $this->getJson('search', Stac::parameters($query, $collections, 100, Stac::CQL2) + ['token' => $query->cursor]);
 *   return Stac::page($data ?? [], 'copernicus', 'token', $query->limit);
 */
final class Stac
{
    /** The cloud cover filtered by the Filter extension, in CQL2 text: filter=eo:cloud_cover<=30 (the Copernicus Data Space). */
    public const CQL2 = 'cql2-text';
    /** The cloud cover filtered by the Query extension: query[eo:cloud_cover][lte]=30 (NASA's CMR-STAC). */
    public const QUERY = 'query';

    /**
     * The GET parameters of an item search: the collections, a BBox (bbox)
     * or a Point (intersects), the period in UTC (".." for an open end),
     * the limit capped, newest or oldest first, the cloud cover in the
     * catalogue's dialect (CQL2 or QUERY). Null values are none.
     *
     * @param list<string> $collections
     *
     * @return array<string, string|int|null>
     */
    public static function parameters(ProductQuery $query, array $collections, int $maxPerPage, string $cloud = self::CQL2): array
    {
        if (!\in_array($cloud, [self::CQL2, self::QUERY], true)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is no cloud filter of a STAC API (Stac::CQL2, Stac::QUERY).', $cloud));
        }
        $cover = null === $query->maxCloudCover ? null : self::number($query->maxCloudCover);

        return [
            'collections' => $collections ? implode(',', $collections) : null,
            'bbox' => $query->place instanceof BBox ? implode(',', array_map(self::number(...), $query->place->toArray())) : null,
            'intersects' => $query->place instanceof Point ? json_encode($query->place->toGeoJson(), \JSON_THROW_ON_ERROR) : null,
            'datetime' => null === $query->from && null === $query->to ? null : self::instant($query->from).'/'.self::instant($query->to),
            'limit' => max(1, min($maxPerPage, $query->limit)),
            // Ascending without its "+": the CMR refuses it, the Data Space reads either.
            'sortby' => ProductQuery::OLDEST === $query->sort ? 'datetime' : '-datetime',
            'filter' => self::CQL2 === $cloud && null !== $cover ? 'eo:cloud_cover<='.$cover : null,
            'filter-lang' => self::CQL2 === $cloud && null !== $cover ? 'cql2-text' : null,
            'query[eo:cloud_cover][lte]' => self::QUERY === $cloud ? $cover : null,
        ];
    }

    /**
     * An item search's answer (a FeatureCollection) as a Page: its items,
     * the value of $cursor in its next link (null on the last page, or on a
     * page shorter than asked), its total (numberMatched, or the Context
     * extension's matched).
     *
     * @param array<string, mixed> $answer
     *
     * @return Page<Product>
     */
    public static function page(array $answer, string $source, string $cursor, ?int $asked = null): Page
    {
        $products = [];
        foreach ($answer['features'] ?? [] as $item) {
            if (\is_array($item) && isset($item['id'])) {
                $products[] = self::item($item, $source);
            }
        }
        $next = null;
        foreach ($answer['links'] ?? [] as $link) {
            if ('next' === ($link['rel'] ?? null) && \is_string($link['href'] ?? null)) {
                parse_str((string) parse_url($link['href'], \PHP_URL_QUERY), $parameters);
                $next = isset($parameters[$cursor]) && \is_string($parameters[$cursor]) ? $parameters[$cursor] : null;
            }
        }
        if (!$products || (null !== $asked && \count($products) < $asked)) {
            $next = null;
        }
        $total = $answer['numberMatched'] ?? $answer['context']['matched'] ?? null;

        return new Page($products, $next, is_numeric($total) ? (int) $total : null);
    }

    /**
     * A STAC item as a Product.
     *
     * @param array<string, mixed> $item
     */
    public static function item(array $item, string $source): Product
    {
        $properties = array_filter(\is_array($item['properties'] ?? null) ? $item['properties'] : [], static fn (string|int $key) => !str_starts_with((string) $key, '_'), \ARRAY_FILTER_USE_KEY);
        $assets = self::assets(\is_array($item['assets'] ?? null) ? $item['assets'] : []);

        return new Product(
            id: (string) $item['id'],
            collection: (string) ($item['collection'] ?? ''),
            datetime: self::time($properties['datetime'] ?? null),
            start: self::time($properties['start_datetime'] ?? null),
            end: self::time($properties['end_datetime'] ?? null),
            bbox: self::box($item['bbox'] ?? null),
            geometry: \is_array($item['geometry'] ?? null) ? $item['geometry'] : null,
            properties: $properties,
            assets: $assets,
            preview: self::preview($assets),
            url: self::link($item, 'self'),
            source: $source,
        );
    }

    /**
     * A STAC collection as a Product: a dataset, its id what an item
     * search's collections take.
     *
     * @param array<string, mixed> $collection
     */
    public static function collection(array $collection, string $source): Product
    {
        $id = (string) ($collection['id'] ?? '');
        $interval = $collection['extent']['temporal']['interval'][0] ?? [null, null];
        $assets = self::assets(\is_array($collection['assets'] ?? null) ? $collection['assets'] : []);
        $providers = \is_array($collection['providers'] ?? null) ? array_values(array_filter(array_map(static fn (mixed $p) => \is_array($p) ? ($p['name'] ?? null) : null, $collection['providers']))) : [];
        $properties = array_filter([
            'title' => $collection['title'] ?? null,
            'description' => $collection['description'] ?? null,
            'keywords' => $collection['keywords'] ?? null,
            'license' => $collection['license'] ?? null,
            'sci:doi' => $collection['sci:doi'] ?? null,
            'providers' => $providers ?: null,
        ], static fn (mixed $v) => null !== $v && '' !== $v && [] !== $v);

        return new Product(
            id: $id,
            collection: $id,
            start: self::time(\is_array($interval) ? ($interval[0] ?? null) : null),
            end: self::time(\is_array($interval) ? ($interval[1] ?? null) : null),
            bbox: self::box($collection['extent']['spatial']['bbox'][0] ?? null),
            properties: $properties,
            assets: $assets,
            preview: self::preview($assets),
            url: self::link($collection, 'self'),
            source: $source,
        );
    }

    /**
     * The assets by name, each with its alternates: the Alternate Assets
     * extension's ("alternate": {"https": {"href"}}), and the CMR's twins
     * (an asset "s3_B04" whose address is s3:// is "B04"'s "s3" alternate).
     *
     * @param array<string, mixed> $raw
     *
     * @return array<string, Asset>
     */
    private static function assets(array $raw): array
    {
        $twins = [];
        foreach ($raw as $name => $asset) {
            $name = (string) $name;
            if (str_starts_with($name, 's3_') && isset($raw[substr($name, 3)]) && \is_string($asset['href'] ?? null) && str_starts_with($asset['href'], 's3://')) {
                $twins[substr($name, 3)] = $asset['href'];
            }
        }
        $assets = [];
        foreach ($raw as $name => $asset) {
            $name = (string) $name;
            if (!\is_array($asset) || !\is_string($asset['href'] ?? null) || (str_starts_with($name, 's3_') && isset($twins[substr($name, 3)]))) {
                continue;
            }
            $alternates = [];
            foreach (\is_array($asset['alternate'] ?? null) ? $asset['alternate'] : [] as $alternate => $other) {
                if (\is_string($other['href'] ?? null)) {
                    $alternates[(string) $alternate] = $other['href'];
                }
            }
            if (isset($twins[$name])) {
                $alternates['s3'] ??= $twins[$name];
            }
            $assets[$name] = new Asset(
                name: $name,
                href: $asset['href'],
                type: isset($asset['type']) ? (string) $asset['type'] : null,
                roles: array_values(array_map('strval', \is_array($asset['roles'] ?? null) ? $asset['roles'] : [])),
                title: isset($asset['title']) ? (string) $asset['title'] : null,
                size: isset($asset['file:size']) && is_numeric($asset['file:size']) ? (int) $asset['file:size'] : null,
                alternates: $alternates,
            );
        }

        return $assets;
    }

    /** @param array<string, Asset> $assets the first thumbnail's https:// address */
    private static function preview(array $assets): ?string
    {
        foreach ($assets as $asset) {
            if ($asset->hasRole('thumbnail') && null !== ($href = $asset->httpsHref())) {
                return $href;
            }
        }

        return null;
    }

    /** @param array<string, mixed> $object */
    private static function link(array $object, string $rel): ?string
    {
        foreach (\is_array($object['links'] ?? null) ? $object['links'] : [] as $link) {
            if ($rel === ($link['rel'] ?? null) && \is_string($link['href'] ?? null)) {
                return $link['href'];
            }
        }

        return null;
    }

    private static function box(mixed $bbox): ?BBox
    {
        if (!\is_array($bbox) || \count($bbox) < 4) {
            return null;
        }
        try {
            return BBox::fromArray(array_map('floatval', array_values($bbox)));
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    private static function time(mixed $time): ?\DateTimeImmutable
    {
        if (!\is_string($time) || '' === $time) {
            return null;
        }
        try {
            return new \DateTimeImmutable($time);
        } catch (\Exception) {
            return null;
        }
    }

    /** An end of the period in RFC 3339, UTC; ".." when open. */
    private static function instant(?\DateTimeInterface $time): string
    {
        return null === $time ? '..' : \DateTimeImmutable::createFromInterface($time)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    private static function number(float $value): string
    {
        return rtrim(rtrim(\sprintf('%.6F', $value), '0'), '.');
    }
}
