<?php

namespace Omnilab\Tests;

use Omnilab\Model\Asset;
use Omnilab\Model\BBox;
use Omnilab\Model\Point;
use Omnilab\Model\Product;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Series;
use Omnilab\Source\Page;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Query;
use Omnilab\Source\SeriesQuery;
use PHPUnit\Framework\TestCase;

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
}
