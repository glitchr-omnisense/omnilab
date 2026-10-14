<?php

namespace Omnilab\Model;

/**
 * One file of a product, as a STAC asset: its address, its media type, its
 * roles ("data", "thumbnail", "overview", "metadata", "visual"...). $href
 * is kept as the catalogue gives it - an https:// or an s3:// address -
 * and $alternates holds the other addresses of the same file by name
 * ("https", "s3"). Whether reading it needs a token is the service's
 * business: its README says.
 */
final readonly class Asset
{
    /**
     * @param list<string>          $roles
     * @param array<string, string> $alternates
     */
    public function __construct(
        public string $name,
        public string $href,
        public ?string $type = null,
        public array $roles = [],
        public ?string $title = null,
        public ?int $size = null,
        public array $alternates = [],
    ) {
    }

    public function hasRole(string $role): bool
    {
        return \in_array($role, $this->roles, true);
    }

    /** The first https:// address: $href when it is one, else an alternate. */
    public function httpsHref(): ?string
    {
        foreach ([$this->href, ...array_values($this->alternates)] as $href) {
            if (str_starts_with($href, 'https://')) {
                return $href;
            }
        }

        return null;
    }
}
