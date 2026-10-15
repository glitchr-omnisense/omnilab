<?php

namespace Omnilab\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Model\Asset;
use Omnilab\Model\BBox;
use Omnilab\Model\Identifier;
use Omnilab\Model\Job;
use Omnilab\Model\Point;
use Omnilab\Model\Product;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Model\Series;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\OccurrenceQuery;
use Omnilab\Source\Page;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Query;
use Omnilab\Source\SeriesQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ModelsTest extends TestCase
{
    public function testQuantitiesAndPropertiesAsText(): void
    {
        self::assertSame('194.19 g/mol', (string) new Quantity(194.19, 'g/mol'));
        self::assertSame('4.6 ± 0.2 km', (string) new Quantity(4.6, 'km', 0.2));
        self::assertSame('3', (string) new Quantity(3));
        self::assertSame('4.6 km', (string) (new Quantity(4600, 'm'))->scaled(0.001, 'km'));
        self::assertSame('-0.1', (string) new Property('xlogp', 'XLogP', -0.1));
        self::assertSame('yes', (string) new Property('aromatic', 'Aromatic', true));
        self::assertSame(194.19, (new Property('w', 'W', new Quantity(194.19, 'g/mol')))->number());
        self::assertNull((new Property('f', 'F', 'C8H10N4O2'))->number());
    }

    public function testPlaces(): void
    {
        $strasbourg = Point::parse('48.5734, 7.7521');
        $box = BBox::parse('7.68,48.53,7.83,48.64');

        self::assertTrue($box->contains($strasbourg));
        self::assertFalse($box->contains(new Point(48.85, 2.35)), 'Paris');
        self::assertTrue($strasbourg->around(5)->contains($strasbourg));
        self::assertEqualsWithDelta(48.585, $box->center()->latitude, 1e-9);
        self::assertSame(['type' => 'Point', 'coordinates' => [7.7521, 48.5734]], $strasbourg->toGeoJson(), 'longitude first');
        self::assertSame([7.68, 48.53, 7.83, 48.64], BBox::fromArray([7.68, 48.53, 0, 7.83, 48.64, 100])->toArray(), 'a 3D bbox\'s elevations dropped');
        self::assertTrue((new BBox(170, -10, -170, 10))->contains(new Point(0, 180)), 'across the antimeridian');
        self::assertSame(180.0, (new BBox(170, -10, -170, 10))->center()->longitude);

        $this->expectException(\InvalidArgumentException::class);
        new Point(91, 0);
    }

    public function testASeries(): void
    {
        $at = static fn (string $t) => new \DateTimeImmutable($t);
        $series = new Series('temperature_2m', '°C', new Point(48.57, 7.73), [[$at('2026-10-16T00:00+02:00'), 12.2], [$at('2026-10-16T01:00+02:00'), null], [$at('2026-10-16T02:00+02:00'), 11.0]], 'PT1H');

        self::assertCount(3, $series);
        self::assertSame(11.0, $series->min());
        self::assertSame(12.2, $series->max());
        self::assertEqualsWithDelta(11.6, $series->mean(), 1e-9, 'nulls left out');
        self::assertSame(12.2, $series->at($at('2026-10-15T22:00Z')), 'the same instant in another zone');
        self::assertNull($series->at($at('2026-10-16T01:00+02:00')));
        self::assertCount(2, $series->between($at('2026-10-16T00:00+02:00'), $at('2026-10-16T02:00+02:00')));
        foreach ($series as [$time, $value]) {
            self::assertInstanceOf(\DateTimeImmutable::class, $time);
        }
    }

    public function testAProductAndItsAssets(): void
    {
        $product = new Product('S2A_X', 'sentinel-2-l2a', properties: ['eo:cloud_cover' => 28.94, 'platform' => 'sentinel-2a'], assets: [
            'TCI_10m' => new Asset('TCI_10m', 's3://eodata/x.jp2', 'image/jp2', ['visual'], alternates: ['https' => 'https://download.example/x.jp2']),
            'thumbnail' => new Asset('thumbnail', 'https://example/x.jpg', 'image/jpeg', ['thumbnail', 'overview']),
        ]);

        self::assertSame(28.94, $product->cloudCover());
        self::assertSame('sentinel-2a', $product->platform());
        self::assertSame('https://download.example/x.jp2', $product->asset('TCI_10m')->httpsHref());
        self::assertSame(['thumbnail'], array_keys($product->assets('overview')));
        self::assertNull((new Product('L', 'sentinel-1-grd'))->cloudCover(), 'radar: no cloud cover');
    }

    public function testAJobIsAProductInTheMaking(): void
    {
        $file = new Asset('data', 'https://object-store.example/x.nc', 'application/netcdf', ['data'], size: 8);
        $done = new Job('9bfc1362', 'reanalysis-era5-single-levels', Job::SUCCESSFUL, 100.0, created: new \DateTimeImmutable('2026-10-15T08:00:00Z'), finished: new \DateTimeImmutable('2026-10-15T08:05:00Z'), properties: ['status' => 'successful'], file: $file, source: 'cds');

        self::assertInstanceOf(Product::class, $done);
        self::assertSame(['reanalysis-era5-single-levels', 'successful', $file, $file, true, true], [$done->collection, $done->properties['status'], $done->file(), $done->asset('data'), $done->isDone(), $done->isSuccessful()]);
        self::assertSame(['2026-10-15T08:00:00+00:00', null, '2026-10-15T08:05:00+00:00'], [$done->datetime->format(\DATE_ATOM), $done->start, $done->end->format(\DATE_ATOM)]);
        $queued = new Job('x', 'd', Job::ACCEPTED);
        self::assertSame([false, false, null, [], null], [$queued->isDone(), $queued->isSuccessful(), $queued->file(), $queued->assets, $queued->progress]);
        self::assertTrue((new Job('y', 'd', Job::FAILED, message: 'no data'))->isDone());

        $source = new class(new MockHttpClient(), 'https://example.org/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }
        };
        foreach ([static fn () => $source->datasets(new Query('era5')), static fn () => $source->open($file)] as $refusal) {
            try {
                $refusal();
                self::fail('not done');
            } catch (NotSupportedException) {
            }
        }
    }

    public function testQueriesRefuseWhatCannotBeAsked(): void
    {
        self::assertSame('2', (new Query('caffeine'))->with(['cursor' => '2'])->cursor);
        self::assertSame(30.0, (new ProductQuery(maxCloudCover: 30))->maxCloudCover);
        self::assertSame('P1D', (new SeriesQuery(['t'], new Point(0, 0)))->with(['step' => SeriesQuery::DAILY])->step);
        self::assertTrue((new Page())->isLast());
        self::assertNull((new Page())->first());
        try {
            new SeriesQuery([], new Point(0, 0));
            self::fail('no variable');
        } catch (\InvalidArgumentException) {
        }
        $this->expectException(\InvalidArgumentException::class);
        new ProductQuery(from: new \DateTimeImmutable('2026-10-15'), to: new \DateTimeImmutable('2026-10-05'));
    }

    public function testASeriesAtAStation(): void
    {
        $central = Identifier::of(Scheme::GHCN, 'USW00094728');
        $query = new SeriesQuery(['TMAX'], $central, step: SeriesQuery::DAILY);
        self::assertSame($central, $query->place);
        self::assertSame('nws.station:KNYC', (string) $query->with(['place' => Identifier::of(Scheme::NWS_STATION, 'KNYC')])->place);

        $source = new class(new MockHttpClient(), 'https://example.org/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [Capability::SERIES, Capability::STATION];
            }

            public function read(SeriesQuery $query, Scheme ...$schemes): ?Identifier
            {
                return $this->station($query, ...$schemes);
            }
        };
        self::assertSame($central, $source->read($query, Scheme::GHCN));
        self::assertNull($source->read(new SeriesQuery(['t'], new Point(0, 0)), Scheme::GHCN), 'a place');
        foreach ([[Scheme::NWS_STATION], []] as $schemes) {
            try {
                $source->read($query, ...$schemes);
                self::fail('a station of a scheme it does not read');
            } catch (NotSupportedException $e) {
                self::assertStringStartsWith('The "example" source does not serve the records of a station of GHCN station', $e->getMessage());
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        new SeriesQuery(['t'], Identifier::of(Scheme::PUBCHEM_CID, '2519'));
    }

    public function testAnOccurrenceQuery(): void
    {
        $skukuza = new Point(-24.99, 31.59);
        $query = new OccurrenceQuery($skukuza, new \DateTimeImmutable('2025-01-01'), new \DateTimeImmutable('2025-12-31'), [OccurrenceQuery::TAXON => '4CGXP'], radius: 100);

        self::assertSame('4CGXP', $query->filter(OccurrenceQuery::TAXON));
        self::assertNull($query->filter(OccurrenceQuery::COUNTRY));
        self::assertSame('300', $query->with(['cursor' => '300'])->cursor);
        self::assertSame(25, $query->limit);
        self::assertNull($query->sort, 'the source\'s own order');
        self::assertNull((new OccurrenceQuery())->place, 'anywhere');
        self::assertSame('2026-10-15', (new OccurrenceQuery(from: new \DateTimeImmutable('2026-10-15T08:00:00+02:00'), to: new \DateTimeImmutable('2026-10-15')))->to->format('Y-m-d'), 'an end at midnight is its whole day: a period from the morning to it');
        foreach ([
            'a radius around a box' => static fn () => new OccurrenceQuery(new BBox(-25, 35, 45, 72), radius: 10),
            'a radius without a place' => static fn () => new OccurrenceQuery(radius: 10),
            'a radius of nothing' => static fn () => new OccurrenceQuery($skukuza, radius: 0),
            'a period backwards' => static fn () => new OccurrenceQuery(from: new \DateTimeImmutable('2026-10-15'), to: new \DateTimeImmutable('2026-10-05')),
        ] as $what => $query) {
            try {
                $query();
                self::fail($what);
            } catch (\InvalidArgumentException) {
            }
        }
    }

    public function testASourceRefusesTheOccurrencesOrTheFiltersItDoesNotRead(): void
    {
        $source = new class(new MockHttpClient(), 'https://example.org/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }

            /** @return array<string, mixed> */
            public function read(OccurrenceQuery $query): array
            {
                return $this->filters($query, OccurrenceQuery::TAXON, OccurrenceQuery::COUNTRY);
            }
        };
        self::assertNotContains(Capability::OCCURRENCES, $source->capabilities());
        self::assertSame(['taxon' => '4CGXP'], $source->read(new OccurrenceQuery(filters: ['taxon' => '4CGXP', 'country' => []])), 'an empty value is no filter');
        try {
            $source->read(new OccurrenceQuery(filters: ['taxon' => '4CGXP', 'min_magnitude' => 4]));
            self::fail('a filter it does not read');
        } catch (NotSupportedException $e) {
            self::assertSame('The "example" source does not filter occurrences by "min_magnitude" (it reads "taxon", "country").', $e->getMessage());
        }
        $this->expectException(NotSupportedException::class);
        $this->expectExceptionMessage('The "example" source does not list occurrences.');
        $source->occurrences(new OccurrenceQuery());
    }

    public function testASourcePostsJsonAndReadsNoContentAsNothing(): void
    {
        $asked = [];
        $answers = [
            new MockResponse('{"data":{"entry":{"rcsb_id":"4HHB"}}}'),
            new MockResponse('', ['http_code' => 204]),
            new MockResponse('{"error":"ID \'ENSG00000000000\' not found"}', ['http_code' => 400]),
            new MockResponse('[1, 2]'),
        ];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$asked, &$answers): MockResponse {
            $asked[] = [$method, $url, $options['body'] ?? null, $options['normalized_headers']['content-type'][0] ?? null];

            return array_shift($answers);
        });
        $source = new class($http, 'https://example.org/', ['User-Agent' => 'test']) extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }

            /** @param array<mixed> $body */
            public function post(string $path, array $body, array $query = []): ?array
            {
                return $this->postForJson($path, $body, $query);
            }

            public function read(string $path): ?array
            {
                return $this->getJson($path);
            }
        };

        self::assertSame(['data' => ['entry' => ['rcsb_id' => '4HHB']]], $source->post('graphql', ['query' => '{entry(entry_id:"4HHB"){rcsb_id}}', 'variables' => ['id' => 'é/1']], ['v' => 1]));
        self::assertSame(['POST', 'https://example.org/graphql?v=1', '{"query":"{entry(entry_id:\"4HHB\"){rcsb_id}}","variables":{"id":"é/1"}}', 'Content-Type: application/json'], $asked[0]);
        self::assertNull($source->post('query', ['q' => 'nothing']), '204: nothing matched');
        try {
            $source->read('lookup/id/ENSG00000000000');
            self::fail('a 400');
        } catch (ProviderException $e) {
            self::assertSame([400, '[example] the service answered 400: ID \'ENSG00000000000\' not found'], [$e->status, $e->getMessage()]);
        }
        self::assertSame([1, 2], $source->read('list'), 'a JSON list is an array too');
    }

    public function testAGetWithAJsonBody(): void
    {
        $asked = [];
        $http = new MockHttpClient(static function (string $method, string $url, array $options) use (&$asked): MockResponse {
            $asked[] = [$method, $url, $options['body'] ?? null, $options['normalized_headers']['content-type'][0] ?? null];

            return new MockResponse('[{"mpc_orb": []}, 200]');
        });
        $source = new class($http, 'https://example.org/api/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }

            /** @param array<mixed> $body */
            public function orbit(array $body): ?array
            {
                return $this->getForJson('get-orb', $body);
            }
        };

        self::assertSame([['mpc_orb' => []], 200], $source->orbit(['desig' => '433']));
        self::assertSame(['GET', 'https://example.org/api/get-orb', '{"desig":"433"}', 'Content-Type: application/json'], $asked[0], 'the Minor Planet Center\'s way: a GET with a JSON body');
    }
}
