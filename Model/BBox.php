<?php

namespace Omnilab\Model;

/**
 * An area: west, south, east and north bounds in WGS 84 degrees, in the
 * order of GeoJSON and STAC. A box that crosses the antimeridian has its
 * west bound greater than its east one.
 *
 *   new BBox(7.68, 48.53, 7.83, 48.64);   // Strasbourg
 */
final readonly class BBox implements \Stringable
{
    public function __construct(
        public float $west,
        public float $south,
        public float $east,
        public float $north,
    ) {
        if ($south > $north || $south < -90 || $north > 90 || $west < -180 || $west > 180 || $east < -180 || $east > 180) {
            throw new \InvalidArgumentException(\sprintf('%s is not a west,south,east,north box.', implode(',', [$west, $south, $east, $north])));
        }
    }

    /** "7.68,48.53,7.83,48.64": west, south, east, north. */
    public static function parse(string $text): self
    {
        $parts = array_map('trim', explode(',', $text));
        if (4 !== \count($parts) || array_filter($parts, static fn (string $p) => !is_numeric($p))) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not "west,south,east,north".', $text));
        }

        return new self(...array_map('floatval', $parts));
    }

    /** @param array{float, float, float, float}|list<float> $bbox a GeoJSON bbox (a 3D one's elevations dropped) */
    public static function fromArray(array $bbox): self
    {
        $bbox = array_values($bbox);

        return 6 === \count($bbox) ? new self((float) $bbox[0], (float) $bbox[1], (float) $bbox[3], (float) $bbox[4]) : new self((float) $bbox[0], (float) $bbox[1], (float) $bbox[2], (float) $bbox[3]);
    }

    public function contains(Point $point): bool
    {
        $inLongitude = $this->west <= $this->east
            ? $point->longitude >= $this->west && $point->longitude <= $this->east
            : $point->longitude >= $this->west || $point->longitude <= $this->east;

        return $inLongitude && $point->latitude >= $this->south && $point->latitude <= $this->north;
    }

    public function center(): Point
    {
        $east = $this->west <= $this->east ? $this->east : $this->east + 360;
        $longitude = ($this->west + $east) / 2;

        return new Point(($this->south + $this->north) / 2, $longitude > 180 ? $longitude - 360 : $longitude);
    }

    /** @return list<float> west, south, east, north */
    public function toArray(): array
    {
        return [$this->west, $this->south, $this->east, $this->north];
    }

    /** As a GeoJSON polygon. */
    public function toGeoJson(): array
    {
        return ['type' => 'Polygon', 'coordinates' => [[[$this->west, $this->south], [$this->east, $this->south], [$this->east, $this->north], [$this->west, $this->north], [$this->west, $this->south]]]];
    }

    public function __toString(): string
    {
        return implode(',', $this->toArray());
    }
}
