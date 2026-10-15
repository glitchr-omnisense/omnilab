<?php

/**
 * Every source package: its slug (omnilab/<slug>, github.com/glitchr-studio/omnilab-<slug>),
 * its tests' namespace and its factory class.
 */
return [
    'pubchem' => ['Omnilab\\PubChem\\Tests\\', 'Omnilab\\PubChem\\PubChemSourceFactory'],
    'open-meteo' => ['Omnilab\\OpenMeteo\\Tests\\', 'Omnilab\\OpenMeteo\\OpenMeteoSourceFactory'],
    'copernicus' => ['Omnilab\\Copernicus\\Tests\\', 'Omnilab\\Copernicus\\CopernicusSourceFactory'],
    'chebi' => ['Omnilab\\Chebi\\Tests\\', 'Omnilab\\Chebi\\ChebiSourceFactory'],
    'wikidata' => ['Omnilab\\Wikidata\\Tests\\', 'Omnilab\\Wikidata\\WikidataSourceFactory'],
    'chembl' => ['Omnilab\\Chembl\\Tests\\', 'Omnilab\\Chembl\\ChemblSourceFactory'],
    'uniprot' => ['Omnilab\\UniProt\\Tests\\', 'Omnilab\\UniProt\\UniProtSourceFactory'],
    'ncbi' => ['Omnilab\\Ncbi\\Tests\\', 'Omnilab\\Ncbi\\NcbiSourceFactory'],
    'gbif' => ['Omnilab\\Gbif\\Tests\\', 'Omnilab\\Gbif\\GbifSourceFactory'],
    'simbad' => ['Omnilab\\Simbad\\Tests\\', 'Omnilab\\Simbad\\SimbadSourceFactory'],
    'jpl' => ['Omnilab\\Jpl\\Tests\\', 'Omnilab\\Jpl\\JplSourceFactory'],
    'usgs' => ['Omnilab\\Usgs\\Tests\\', 'Omnilab\\Usgs\\UsgsSourceFactory'],
    'ensembl' => ['Omnilab\\Ensembl\\Tests\\', 'Omnilab\\Ensembl\\EnsemblSourceFactory'],
    'pdb' => ['Omnilab\\Pdb\\Tests\\', 'Omnilab\\Pdb\\PdbSourceFactory'],
    'inaturalist' => ['Omnilab\\INaturalist\\Tests\\', 'Omnilab\\INaturalist\\INaturalistSourceFactory'],
];
