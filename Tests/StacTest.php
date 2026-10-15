<?php

namespace Omnilab\Tests;

use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Stac;
use PHPUnit\Framework\TestCase;

/**
 * The shapes below are those the Copernicus Data Space's and the CMR's STAC
 * catalogues answered on 2026-10-15, cut to what is read.
 */
final class StacTest extends TestCase
{
    public function testAQueryAsAnItemSearch(): void
    {
        $query = new ProductQuery(['sentinel-2-l2a'], new Point(48.5734, 7.7521), new \DateTimeImmutable('2026-10-05T02:00:00+02:00'), null, 30, 500);

        self::assertSame([
            'collections' => 'sentinel-2-l2a',
            'bbox' => null,
            'intersects' => '{"type":"Point","coordinates":[7.7521,48.5734]}',
            'datetime' => '2026-10-05T00:00:00Z/..',
            'limit' => 100,
            'sortby' => '-datetime',
            'filter' => 'eo:cloud_cover<=30',
            'filter-lang' => 'cql2-text',
            'query[eo:cloud_cover][lte]' => null,
        ], Stac::parameters($query, $query->collections, 100));

        $cmr = Stac::parameters(new ProductQuery(['HLSS30_2.0', 'HLSL30_2.0'], new BBox(7.68, 48.53, 7.83, 48.64), maxCloudCover: 12.5, limit: 10, sort: ProductQuery::OLDEST), ['HLSS30_2.0', 'HLSL30_2.0'], 50, Stac::QUERY);
        self::assertSame(['HLSS30_2.0,HLSL30_2.0', '7.68,48.53,7.83,48.64', null, 10, 'datetime', null, '12.5'], [$cmr['collections'], $cmr['bbox'], $cmr['datetime'], $cmr['limit'], $cmr['sortby'], $cmr['filter'], $cmr['query[eo:cloud_cover][lte]']]);

        $this->expectException(\InvalidArgumentException::class);
        Stac::parameters($query, [], 10, 'odata');
    }

    public function testAPageOfItems(): void
    {
        $answer = [
            'type' => 'FeatureCollection',
            'features' => [[
                'id' => 'HLS.S30.T32ULU.2026278T102859.v2.0',
                'collection' => 'HLSS30_2.0',
                'bbox' => [6.2655, 47.7462, 7.8, 48.7364],
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[6.2655, 47.7462], [7.7462, 47.7462], [7.7462, 48.7364], [6.2655, 47.7462]]]],
                'properties' => ['datetime' => '2026-10-05T10:37:40.123Z', 'start_datetime' => '2026-10-05T10:37:40.123Z', 'end_datetime' => '2026-10-05T10:37:40.123Z', 'eo:cloud_cover' => 12, '_private' => ['x' => 1]],
                'assets' => [
                    'B04' => ['href' => 'https://data.lpdaac.earthdatacloud.nasa.gov/lp-prod-protected/HLSS30.020/x/x.B04.tif', 'title' => 'Download x.B04.tif', 'roles' => ['data']],
                    's3_B04' => ['href' => 's3://lp-prod-protected/HLSS30.020/x/x.B04.tif', 'roles' => ['data']],
                    'TCI' => ['href' => 's3://eodata/x/TCI.jp2', 'type' => 'image/jp2', 'roles' => ['visual'], 'file:size' => 131072, 'alternate' => ['https' => ['href' => 'https://download.dataspace.copernicus.eu/x/TCI.jp2']]],
                    'thumbnail' => ['href' => 'https://datahub.creodias.eu/odata/v1/Assets(1)/$value', 'type' => 'image/jpeg', 'roles' => ['thumbnail', 'overview']],
                ],
                'links' => [['rel' => 'self', 'href' => 'https://cmr.earthdata.nasa.gov/stac/LPCLOUD/collections/HLSS30_2.0/items/x']],
            ]],
            'links' => [['rel' => 'next', 'href' => 'https://cmr.earthdata.nasa.gov/stac/LPCLOUD/search?collections=HLSS30_2.0&limit=1&cursor=eyJqc29u']],
            'context' => ['returned' => 1, 'limit' => 1, 'matched' => 2],
        ];
        $page = Stac::page($answer, 'earthdata', 'cursor', 1);

        self::assertSame(['eyJqc29u', 2], [$page->next, $page->total]);
        $granule = $page->first();
        self::assertSame(['HLS.S30.T32ULU.2026278T102859.v2.0', 'HLSS30_2.0', 'earthdata', 12.0], [$granule->id, $granule->collection, $granule->source, $granule->cloudCover()]);
        self::assertSame('2026-10-05T10:37:40+00:00', $granule->datetime->format(\DATE_ATOM));
        self::assertArrayNotHasKey('_private', $granule->properties);
        self::assertSame(['B04', 'TCI', 'thumbnail'], array_keys($granule->assets), 'the CMR\'s s3_ twin folded into its asset');
        self::assertSame('s3://lp-prod-protected/HLSS30.020/x/x.B04.tif', $granule->asset('B04')->alternates['s3']);
        self::assertSame(['https://download.dataspace.copernicus.eu/x/TCI.jp2', 131072], [$granule->asset('TCI')->httpsHref(), $granule->asset('TCI')->size]);
        self::assertSame('https://datahub.creodias.eu/odata/v1/Assets(1)/$value', $granule->preview);
        self::assertSame('https://cmr.earthdata.nasa.gov/stac/LPCLOUD/collections/HLSS30_2.0/items/x', $granule->url);
        self::assertTrue($granule->bbox->contains(new Point(48.5734, 7.7521)));

        self::assertNull(Stac::page($answer, 'earthdata', 'cursor', 2)->next, 'a page shorter than asked is the last');
        self::assertNull(Stac::page(['features' => [], 'links' => $answer['links']], 'earthdata', 'cursor')->next, 'an empty page is the last');
        self::assertSame(['next:sentinel-2-l2a:S2A', null], [Stac::page(['features' => $answer['features'], 'links' => [['rel' => 'next', 'href' => 'https://stac.dataspace.copernicus.eu/v1/search?collections=sentinel-2-l2a&filter=eo:cloud_cover<=30&token=next:sentinel-2-l2a:S2A']]], 'copernicus', 'token')->next, Stac::page(['features' => $answer['features'], 'numberMatched' => null], 'copernicus', 'token')->total]);
    }

    public function testACollectionAsADataset(): void
    {
        $dataset = Stac::collection([
            'id' => 'sentinel-2-l2a',
            'title' => 'Sentinel-2 Level-2A',
            'description' => 'Bottom-of-atmosphere reflectances.',
            'keywords' => ['Sentinel', 'Copernicus'],
            'license' => 'other',
            'providers' => [['name' => 'ESA'], ['name' => 'CloudFerro']],
            'extent' => ['spatial' => ['bbox' => [[-180, -90, 180, 90]]], 'temporal' => ['interval' => [['2015-07-04T00:00:00Z', null]]]],
            'links' => [['rel' => 'self', 'href' => 'https://stac.dataspace.copernicus.eu/v1/collections/sentinel-2-l2a']],
        ], 'copernicus');

        self::assertSame(['sentinel-2-l2a', 'sentinel-2-l2a', 'Sentinel-2 Level-2A', ['ESA', 'CloudFerro']], [$dataset->id, $dataset->collection, $dataset->properties['title'], $dataset->properties['providers']]);
        self::assertSame(['2015-07-04', null, [-180.0, -90.0, 180.0, 90.0]], [$dataset->start->format('Y-m-d'), $dataset->end, $dataset->bbox->toArray()]);
        self::assertSame('https://stac.dataspace.copernicus.eu/v1/collections/sentinel-2-l2a', $dataset->url);
    }
}
