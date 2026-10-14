<?php

namespace Omnilab\Source;

/** Builds a source from its options (a contact address, a key, a base URI...). */
interface SourceFactoryInterface
{
    /** The name sources are configured with: "pubchem", "open-meteo", "copernicus"... */
    public function getName(): string;

    /** @param array<string, mixed> $options */
    public function create(array $options = []): SourceInterface;
}
