<?php

/**
 * The sources, their options from the environment (.env): a source is
 * configured only when every key under "needs" is set (none needs one).
 *
 * @return array<string, array{factory: string, needs: list<string>, options: array<string, mixed>}>
 */
$env = static fn (string $key, mixed $default = null): mixed => (false !== ($v = getenv($key)) && '' !== $v) ? $v : $default;
$mailto = $env('OMNILAB_MAILTO');

return [
    'pubchem' => ['factory' => 'pubchem', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'open-meteo' => ['factory' => 'open-meteo', 'needs' => [], 'options' => ['mailto' => $mailto, 'api_key' => $env('OPEN_METEO_API_KEY'), 'base_uri' => $env('OPEN_METEO_BASE_URI')]],
    'copernicus' => ['factory' => 'copernicus', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'chebi' => ['factory' => 'chebi', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'wikidata' => ['factory' => 'wikidata', 'needs' => [], 'options' => ['mailto' => $mailto, 'language' => $env('WIKIDATA_LANGUAGE')]],
];
