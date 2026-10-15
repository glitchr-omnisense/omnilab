<?php

namespace Omnilab\Model;

/**
 * A catalogued product: a satellite scene, a tile of a mosaic, a granule, a
 * dataset's file - in the shape of a STAC item. Its footprint is a BBox and,
 * when the catalogue gives it, the GeoJSON geometry; its properties are kept
 * as the catalogue names them ("eo:cloud_cover", "platform",
 * "sat:relative_orbit"...), its assets by name.
 *
 * $datetime is the nominal time; $start and $end the interval when the
 * product covers one (a mosaic, an average).
 *
 * Not final: a Job (a product being made on request) extends it.
 */
readonly class Product
{
    /**
     * @param array<string, mixed>|null $geometry   GeoJSON
     * @param array<string, mixed>      $properties
     * @param array<string, Asset>      $assets
     */
    public function __construct(
        public string $id,
        public string $collection,
        public ?\DateTimeImmutable $datetime = null,
        public ?\DateTimeImmutable $start = null,
        public ?\DateTimeImmutable $end = null,
        public ?BBox $bbox = null,
        public ?array $geometry = null,
        public array $properties = [],
        public array $assets = [],
        public ?string $preview = null,
        public ?string $url = null,
        public ?string $source = null,
    ) {
    }

    /** The cloud cover in percent, for an optical scene that says ("eo:cloud_cover"). */
    public function cloudCover(): ?float
    {
        $cover = $this->properties['eo:cloud_cover'] ?? null;

        return is_numeric($cover) ? (float) $cover : null;
    }

    /** "sentinel-2a", "landsat-9"... ("platform"). */
    public function platform(): ?string
    {
        return isset($this->properties['platform']) ? (string) $this->properties['platform'] : null;
    }

    public function asset(string $name): ?Asset
    {
        return $this->assets[$name] ?? null;
    }

    /** @return array<string, Asset> the assets that have that role */
    public function assets(string $role): array
    {
        return array_filter($this->assets, static fn (Asset $a) => $a->hasRole($role));
    }
}
