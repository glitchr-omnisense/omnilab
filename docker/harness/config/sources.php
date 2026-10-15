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
    'air-quality' => ['factory' => 'open-meteo', 'needs' => [], 'options' => ['mailto' => $mailto, 'api' => 'air_quality', 'api_key' => $env('OPEN_METEO_API_KEY')]],
    'copernicus' => ['factory' => 'copernicus', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'chebi' => ['factory' => 'chebi', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'wikidata' => ['factory' => 'wikidata', 'needs' => [], 'options' => ['mailto' => $mailto, 'language' => $env('WIKIDATA_LANGUAGE')]],
    'chembl' => ['factory' => 'chembl', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'uniprot' => ['factory' => 'uniprot', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'ncbi' => ['factory' => 'ncbi', 'needs' => [], 'options' => ['mailto' => $mailto, 'api_key' => $env('NCBI_API_KEY')]],
    'gbif' => ['factory' => 'gbif', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'simbad' => ['factory' => 'simbad', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'jpl' => ['factory' => 'jpl', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'usgs' => ['factory' => 'usgs', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'ensembl' => ['factory' => 'ensembl', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'pdb' => ['factory' => 'pdb', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'noaa' => ['factory' => 'noaa', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'nws' => ['factory' => 'noaa', 'needs' => [], 'options' => ['mailto' => $mailto, 'api' => 'nws']],
    'cds' => ['factory' => 'cds', 'needs' => [], 'options' => ['mailto' => $mailto, 'key' => $env('CDS_KEY')]],
    'cams' => ['factory' => 'cams', 'needs' => [], 'options' => ['mailto' => $mailto, 'key' => $env('ADS_KEY')]],
    'earthdata' => ['factory' => 'earthdata', 'needs' => [], 'options' => ['mailto' => $mailto, 'token' => $env('EARTHDATA_TOKEN')]],
    'exoplanets' => ['factory' => 'exoplanets', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'gaia' => ['factory' => 'gaia', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'reactome' => ['factory' => 'reactome', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'inaturalist' => ['factory' => 'inaturalist', 'needs' => [], 'options' => ['mailto' => $mailto, 'locale' => $env('INATURALIST_LOCALE')]],
    'kegg' => ['factory' => 'kegg', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'rhea' => ['factory' => 'rhea', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'string' => ['factory' => 'string', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'interpro' => ['factory' => 'interpro', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'quickgo' => ['factory' => 'quickgo', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'hgnc' => ['factory' => 'hgnc', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'opentargets' => ['factory' => 'opentargets', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'clinvar' => ['factory' => 'clinvar', 'needs' => [], 'options' => ['mailto' => $mailto, 'api_key' => $env('NCBI_API_KEY')]],
    'gwas' => ['factory' => 'gwas', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'cod' => ['factory' => 'cod', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'cactus' => ['factory' => 'cactus', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'codata' => ['factory' => 'codata', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'materials-project' => ['factory' => 'materials-project', 'needs' => ['MP_API_KEY'], 'options' => ['mailto' => $mailto, 'api_key' => $env('MP_API_KEY')]],
    'oqmd' => ['factory' => 'oqmd', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'emsc' => ['factory' => 'emsc', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'usgs-water' => ['factory' => 'usgs-water', 'needs' => [], 'options' => ['mailto' => $mailto, 'api_key' => $env('USGS_WATER_API_KEY')]],
    'tides' => ['factory' => 'tides', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'swpc' => ['factory' => 'swpc', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'brightsky' => ['factory' => 'brightsky', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'elevation' => ['factory' => 'elevation', 'needs' => [], 'options' => ['mailto' => $mailto]],
    'openaq' => ['factory' => 'openaq', 'needs' => ['OPENAQ_API_KEY'], 'options' => ['mailto' => $mailto, 'api_key' => $env('OPENAQ_API_KEY')]],
];
