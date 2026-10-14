<?php

namespace Omnilab\Model;

/**
 * A place on the Earth: WGS 84 latitude and longitude in degrees, the
 * elevation in metres above sea level when known.
 *
 *   new Point(48.5734, 7.7521);   // Strasbourg
 */
final readonly class Point implements \Stringable
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public ?float $elevation = null,
    ) {
        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            throw new \InvalidArgumentException(\sprintf('%F, %F is not a latitude and a longitude.', $latitude, $longitude));
        }
    }

    /** "48.5734,7.7521": latitude first, as people write it. */
    public static function parse(string $text): self
    {
        $parts = array_map('trim', explode(',', $text));
        if (!\in_array(\count($parts), [2, 3], true) || array_filter($parts, static fn (string $p) => !is_numeric($p))) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not "latitude,longitude".', $text));
        }

        return new self((float) $parts[0], (float) $parts[1], isset($parts[2]) ? (float) $parts[2] : null);
    }

    /** The box of the given half-size around it, in kilometres (approximate away from the poles). */
    public function around(float $kilometres): BBox
    {
        $lat = $kilometres / 111.32;
        $lon = $kilometres / (111.32 * max(0.01, cos(deg2rad($this->latitude))));

        return new BBox(max(-180, $this->longitude - $lon), max(-90, $this->latitude - $lat), min(180, $this->longitude + $lon), min(90, $this->latitude + $lat));
    }

    /** As GeoJSON: longitude first. */
    public function toGeoJson(): array
    {
        return ['type' => 'Point', 'coordinates' => null === $this->elevation ? [$this->longitude, $this->latitude] : [$this->longitude, $this->latitude, $this->elevation]];
    }

    public function __toString(): string
    {
        return $this->latitude.','.$this->longitude;
    }
}
