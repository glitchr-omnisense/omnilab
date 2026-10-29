---
title: Models and identifiers
order: 20
---

# Models and identifiers

All models are read-only (`readonly` classes); `Entity::with([...])` and the queries' `with([...])`
give modified copies.

## Entity

| Field | |
|---|---|
| `id` | the `Identifier` the source keeps it under |
| `label` | its name |
| `domain` | `Domain`: `chemistry`, `biology`, `taxonomy`, `astronomy`, `earth`, `other` |
| `kind` | the source's word for what it is: `compound`, `protein`, `species`, `galaxy`, `earthquake` |
| `synonyms` | the other names it goes by |
| `description` | |
| `properties` | `Property` list: `key`, `label`, `value` (a `Quantity`, a text, a number, a boolean), `source` |
| `identifiers` | `Identifiers`: its own, then the cross-references to other bases - other names of the same thing; `identifiers()` adds `id` |
| `related` | `array<string, Identifiers>`: other things it is tied to, by relation - a protein's `organism` (`ncbi.taxon:9606`), its `structure`s (`pdb:1A3N`), its `ligand`s and `cofactor`s (`chebi:60344`), a taxon's `parent`, an occurrence's `taxon`; `related($relation)` gives one relation's ([why a field of its own](decisions.md)) |
| `url`, `image` | its page; its picture (a structure, where the source has `Capability::STRUCTURE`) |
| `time`, `place` | for what happens somewhere at a moment (an earthquake, an occurrence of a species) or sits somewhere (a station): a `Point` or a `BBox`; every entity `occurrences()` answers has both ([occurrences](occurrences.md)) |
| `source`, `sources` | the source of the record; after a merge, every source merged |

Identifiers name the thing itself: the `Collector` follows them into the other sources, and the
`Merger` takes two entities that share one for the same thing. Related things are not followed and
join nothing: a protein and the human species share the taxon 9606 without being one.

`property($key)` gives the preferred source's property of a key, `properties($key)` every source's,
`value($key)` its value, `identifier(Scheme::CAS)` the first value of a scheme, `keys()` the keys
known.

Property keys are lower case, snake case, and mean the same from one source to the next:
`molecular_formula`, `molecular_weight`, `exact_mass`, `monoisotopic_mass`, `iupac_name`,
`smiles`, `inchi`, `inchikey`, `xlogp`, `tpsa`, `complexity`, `charge`, `h_bond_donors`,
`h_bond_acceptors`, `rotatable_bonds`, `heavy_atoms` (PubChem); `chebi_stars`, `is_a`,
`has_role`, `has_functional_parent`, `is_conjugate_acid_of`... (ChEBI: one key per relation of its
ontology); `connectivity_smiles`, `mass`, `melting_point`, `boiling_point`, `flash_point`,
`density`, `pka`, `taxon_name`, `radius`, `orbital_period`, `redshift`, `distance_from_earth`,
`elevation`, `richter_magnitude` (Wikidata); `gene`, `organism`, `organism_common_name`,
`sequence_length`, `sequence`, `reviewed`, `protein_existence`, `annotation_score`, `ec_number`,
`subcellular_location`, `cofactor`, `ligand`, `keyword` (UniProt; `mass` as Wikidata's); `gene_name`,
`gene_type`, `chromosome`, `map_location`, `genomic_location`, `taxon_rank`, `taxon_authority`,
`common_name`, `group`, `basionym`, `lineage` and one key per rank of the lineage (`kingdom`,
`phylum`, `class`, `order`, `family`, `genus`) (NCBI; `taxon_name` as Wikidata's). A new source reuses a key that exists before it makes
one; a key may hold several values of one source (`properties('has_role')`).

## Property

`Model\Property` (`readonly`, `Stringable`): one thing known of an entity.

| Field | Type | |
|---|---|---|
| `key` | `string` | lower case, snake case, the same from one source to the next when they mean the same (above) |
| `label` | `string` | to show: `Molecular weight` |
| `value` | `Quantity\|string\|int\|float\|bool` | a `Quantity` when it has a unit, else a text, a number or a boolean |
| `source` | `?string` | the source that said it: the `Merger` keeps it through a merge |

`number()` gives the number (a `Quantity`'s included; null for a text or a boolean), `unit()` a
`Quantity`'s unit (null otherwise), `with([...])` a modified copy, and `(string)` the value as
text: `194.19 g/mol`, `C8H10N4O2`, `yes` / `no` for a boolean, a float as a `Quantity` without
unit writes it.

```php
new Property('molecular_weight', 'Molecular weight', new Quantity(194.19, 'g/mol'), 'pubchem');
new Property('molecular_formula', 'Molecular formula', 'C8H10N4O2', 'pubchem');
```

## Domain

`Model\Domain`, a string-backed enum, says what an entity is a thing of - coarse on purpose (a
source says what kind of thing within its domain in `Entity::$kind`, in its own word):

| Case | Value | `label()` | Holds |
|---|---|---|---|
| `CHEMISTRY` | `chemistry` | Chemistry | compounds, substances, elements, reactions, materials |
| `BIOLOGY` | `biology` | Biology | genes, proteins, structures, pathways, variants, diseases |
| `TAXONOMY` | `taxonomy` | Taxonomy | species and the other ranks of the tree of life, and where they were found (occurrences) |
| `ASTRONOMY` | `astronomy` | Astronomy | stars, galaxies, planets, small bodies, space weather events |
| `EARTH` | `earth` | Earth sciences | the solid earth, its oceans and its atmosphere: earthquakes, stations, places |
| `OTHER` | `other` | Other | what none of the above holds: a physical constant, a launch |

`Query::$domain` narrows a search to one domain; `Scheme::domain()` gives a scheme's.

## Quantity

A number, its unit as the source spells it (`g/mol`, `Å²`, `°C`, `mm`), and an uncertainty when
given. No conversion is made; `scaled($factor, $unit)` gives another unit when the caller knows
the factor. `(string) new Quantity(194.19, 'g/mol')` is `194.19 g/mol`; a float below 1e-4 or from
1e9 up is written in scientific notation with every significant digit it holds, its uncertainty in
the same exponent: CODATA's proton mass is `1.67262192595e-27 ± 0.00000000052e-27 kg`
([why](decisions.md)). The number itself (`value`) is never rounded.

## Series

| Field | |
|---|---|
| `variable`, `unit`, `label` | in the source's names: `temperature_2m`, `°C` |
| `place` | the `Point` (or `BBox`) that answered: a model's grid point may not be the point asked |
| `points` | `list<[\DateTimeImmutable, int|float|null]>`, in time order; null where there is no value |
| `step` | an ISO 8601 duration: `PT1H`, `PT15M`, `P1D`; null when irregular |
| `aggregation` | what a point of a step stands for: `Series::MEAN`, `MAX`, `MIN`, `SUM`; null for an instant value |
| `forecast` | true for a forecast, false for observed or reanalysed values, null when mixed |

A series iterates over its points (`foreach ($series as [$time, $value])`) and counts them;
`values()`, `times()`, `at($time)`, `min()`, `max()`, `mean()`, `between($from, $to)`.

`SeriesQuery(variables, place, from, to, step, aggregation, timezone)`: the place a `Point`, a
`BBox` (`Capability::AREA`) or a station's `Identifier` (`Capability::STATION`, below); both ends of the period
included, a date without a time standing for its whole day; the time zone the times and the days
are given in (`auto` is the point's own, at Open-Meteo). The variables are the source's names: the
weather's `temperature_2m`, the air's `pm2_5`, `nitrogen_dioxide`, `european_aqi` (Open-Meteo), the
earthquakes' `count` (the USGS).

## Stations

A station's records are a series of their own: the station that measured them, not the nearest
grid point nor whichever station a source picks. `SeriesQuery::$place` takes a station's
`Identifier` in place of a `Point` - a source with `Capability::STATION` reads the stations of
its schemes, any other refuses a station before any call -, and the series answered carry the
station's `Point` (its elevation too):

| Scheme | A station of | Normalised as |
|---|---|---|
| `ghcn` | the Global Historical Climatology Network (NCEI's GHCN-Daily and its monthly and yearly summaries, the whole world) | eleven characters, upper case: the country (FIPS), the network, the station's own (`USW00094728`, Central Park; `FR000007190`, Strasbourg-Entzheim; `BR00A6-0010`) |
| `wigos` | the WMO's register (OSCAR/Surface): any observing station | its WIGOS id, `0-20000-0-07190`; a WMO index of five digits, written `wmo:07190`, is read as its WIGOS id (Wikidata's P4136) |
| `nws.station` | the U.S. National Weather Service's observation stations | upper case: an ICAO airport (`KNYC`), a buoy (`44069`), a network's (`A4080`) |
| `usgs.site` | the USGS's water monitoring locations (streamgages, wells, springs; the Water Data APIs) | `USGS-01646500`: the agency's code then eight to fifteen digits; a number alone given the scheme (`usgs.site:01646500`) is the USGS's; its page and its record in the API read |
| `coops.station` | NOAA CO-OPS's water level and tide stations (Tides and Currents) | seven digits (`8518750`, The Battery); `coops:` read; its page and its record in the metadata API read |
| `dwd.station` | the Deutscher Wetterdienst's stations | five characters (`01420`, Frankfurt/Main; `P0291`), digits padded with zeros (`dwd:1420`); the DWD has no page for a station |

Only the WIGOS id and a USGS site (`USGS-01646500`) are told by their shape alone; a GHCN, an
NWS, a CO-OPS or a DWD station needs its scheme (`ghcn:USW00094728`, `nws.station:KNYC`,
`coops:8518750`, `dwd:01420`) or its page, as its shape is any code's.

```php
$series = $noaa->series(new SeriesQuery(['TMAX', 'PRCP'], Identifier::of(Scheme::GHCN, 'USW00094728'), new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-07'), SeriesQuery::DAILY));
$series[0]->place;   // 40.77898,-73.96925 (42.7 m)
$series[0]->label;   // NY CITY CENTRAL PARK, NY US (daily-summaries:USW00094728)

$station = $noaa->entity('ghcn:USW00094728'); // the station itself, an Entity of kind "station"
$station->place;                         // its Point and elevation
$station->value('period_start');         // the first day it has records of
$station->properties('variable');        // each element and the period it covers: "TMAX: 1869-01-01/2026-10-10"
```

(see [omnilab/noaa](https://github.com/glitchr-omnisense/omnilab-noaa) for what each API answers)

## Occurrences

No model of their own: `occurrences(OccurrenceQuery)` answers a `Page` of `Entity`, each with its
`time` and its `place`. `OccurrenceQuery(place, from, to, filters, radius, limit, cursor, sort)`:
a `BBox`, or a `Point` and a radius in kilometres; the period; the filters of a domain by key
(`OccurrenceQuery::TAXON`, `COUNTRY`, `KIND`, `MIN_MAGNITUDE`, `MAX_MAGNITUDE`, or a source's own);
`filter($key)` reads one. See [occurrences](occurrences.md).

## Product

A STAC item: `id`, `collection`, `datetime` (and `start`, `end` for an interval), `bbox` and the
GeoJSON `geometry` of its footprint, `properties` as the catalogue names them (`eo:cloud_cover`,
`platform`, `sat:relative_orbit`, `grid:code`...), `assets` by name, `preview` (an open thumbnail),
`url` (the item itself), `source`. `cloudCover()`, `platform()`, `asset($name)`,
`assets($role)`.

An `Asset` has its `href` as given (an `s3://` or an `https://` address), its `type`, `roles`
(`data`, `visual`, `thumbnail`, `overview`, `metadata`), `title`, `size`, and its `alternates` by
name; `httpsHref()` gives the first `https://` one. Whether it can be read without a token is the
service's business (its README says).

`ProductQuery(collections, place, from, to, maxCloudCover, limit, cursor, sort)`.

### Reading a STAC API: `Source\Stac`

The catalogues that speak the STAC API (the Copernicus Data Space's, NASA's CMR-STAC, Planetary
Computer's, Element 84's Earth Search...) are read the same way, so the core reads them once:
`Stac` writes a `ProductQuery` as an item search's parameters and reads its answer back into
`Product`s. A source still makes the call itself (its own `getJson()`, throttle and errors).

| | |
|---|---|
| `Stac::parameters($query, $collections, $maxPerPage, $cloud)` | `collections`, `bbox` (a `BBox`) or `intersects` (a `Point`, as GeoJSON), `datetime` (RFC 3339 in UTC, `..` for an open end), `limit` (capped), `sortby` (`-datetime`, or `datetime` for the oldest first: the `+` some catalogues refuse is left out), the cloud cover in the catalogue's dialect: `Stac::CQL2` (`filter=eo:cloud_cover<=30&filter-lang=cql2-text`, the Filter extension) or `Stac::QUERY` (`query[eo:cloud_cover][lte]=30`, the Query extension) |
| `Stac::page($answer, $source, $cursor, $asked)` | a `Page`: the items as products, `next` the value of the next link's `$cursor` parameter (`token` at the Data Space, `cursor` at the CMR) - null on a short page -, `total` from `numberMatched` or the Context extension's `matched` |
| `Stac::item($item, $source)` | a `Product`: its properties but the catalogue's private ones (`_...`), its assets with their alternates (the Alternate Assets extension, or the CMR's `s3_` twins), `file:size`, the first open thumbnail as `preview`, the `self` link as `url` |
| `Stac::collection($collection, $source)` | a collection as a `Product` (a dataset): its id as `collection`, its extent's box and interval, title, description, keywords, licence, DOI, providers, assets |

```php
use Omnilab\Model\Point;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Stac;
use Symfony\Component\HttpClient\HttpClient;

$query = new ProductQuery(['HLSS30_2.0'], new Point(48.5734, 7.7521), new \DateTimeImmutable('2026-10-01T00:00:00Z'), new \DateTimeImmutable('2026-10-14T23:59:59Z'), maxCloudCover: 30, limit: 1);
$answer = HttpClient::create()->request('GET', 'https://cmr.earthdata.nasa.gov/stac/LPCLOUD/search', ['query' => Stac::parameters($query, $query->collections, 50, Stac::QUERY)])->toArray();
$page = Stac::page($answer, 'earthdata', 'cursor', $query->limit);
printf("%d matched; %s, %s %% cloud, B04 at %s\n", $page->total, $page->first()->id, $page->first()->cloudCover(), $page->first()->asset('B04')->alternates['s3']);
```

```
2 matched; HLS.S30.T32ULU.2026278T102859.v2.0, 12 % cloud, B04 at s3://lp-prod-protected/HLSS30.020/HLS.S30.T32ULU.2026278T102859.v2.0/HLS.S30.T32ULU.2026278T102859.v2.0.B04.tif
```

(as answered on 2026-10-15). omnilab/copernicus reads its catalogue so; omnilab/earthdata reads
the CMR's STAC when configured to (`stac: LPCLOUD`, one archive a catalogue) - it keeps the CMR's
own search by default, which answers every archive at once and was faster (3.5 to 6.5 s against 4
to 16.6 s for the same query, three times each, that day).

## Places

`Point(latitude, longitude, ?elevation)` in WGS 84 degrees and metres; `Point::parse('48.57,7.75')`,
`around($km)` gives a box, `toGeoJson()` writes longitude first. `BBox(west, south, east, north)`
in the order of GeoJSON and STAC; a box across the antimeridian has its west greater than its
east; `BBox::parse()`, `fromArray()` (a 3D bbox too), `contains()`, `center()`, `toGeoJson()`.

## Identifier

```php
Identifier::parse('CHEBI:27732');                                    // chebi:27732
Identifier::parse('58-08-2');                                        // cas:58-08-2 (check digit checked)
Identifier::parse('RYYVLZVUVIJVGH-UHFFFAOYSA-N');                    // inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N
Identifier::parse('https://www.uniprot.org/uniprotkb/P69905/entry'); // uniprot:P69905
Identifier::parse('https://identifiers.org/ncbigene:7157');          // ncbi.gene:7157
Identifier::parse('ncbigene:7157');                                  // identifiers.org's prefixes are read
Identifier::of(Scheme::GBIF, '4CGXP')->url();                        // https://www.gbif.org/taxon/4CGXP
Identifier::of(Scheme::GBIF, '5219404')->url();                      // https://www.gbif.org/species/5219404 (a legacy key)
Identifier::tryOf('cas', '58-08-3');                                 // null: wrong check digit
```

| Scheme | Domain | Normalised as |
|---|---|---|
| `pubchem.cid` | chemistry | the number (`CID 2519` read) |
| `chebi` | chemistry | the number, without `CHEBI:` (`display()` gives it back) |
| `chembl` | chemistry | `CHEMBL113` |
| `inchikey`, `inchi` | chemistry | upper case; the InChI as written |
| `cas` | chemistry | `58-08-2`, check digit checked |
| `unii`, `dsstox`, `kegg` | chemistry | `3G6A5W338E` (`UNII-` read), `DTXSID...`, `C00001` / `D00528` |
| `kegg.reaction`, `kegg.pathway` | chemistry, biology | a KEGG reaction `R00200` (`rn:` read); a KEGG pathway map, lower case: `map00010`, an organism's `hsa00010`, its KO, enzyme or reaction view `ko00010`, `ec00010`, `rn00010` (`path:` read); KEGG's entry pages read for both and for an enzyme (`ec:2.7.1.40`) |
| `rhea` | chemistry | a Rhea reaction, its number: `18157` (`RHEA:18157` read and given back by `display()`); any of a reaction's four ids (the master, left to right, right to left, both ways); its page `rhea-db.org/rhea/` read |
| `ec` | biology | an EC number of the IUBMB's Enzyme Nomenclature, four levels, the last ones `-` when incomplete, `n` before a preliminary one (`2.7.1.40`, `2.7.1.-`, `3.5.1.n3`); `EC 2.7.1.40` read by its shape, `EC:`, `ec-code:` by their prefix (`display()` gives `EC 2.7.1.40`); ExPASy's ENZYME page, the ExplorEnz page and KEGG's read |
| `cod`, `materialsproject`, `oqmd` | chemistry | a crystal structure of the Crystallography Open Database, seven digits (`1000000`; its page and its CIF read; Wikidata's P9824); a Materials Project material `mp-149`, lower case, read by its shape and its page (Wikidata's P9902); an OQMD entry, its number, its page `oqmd.org/materials/entry/` read |
| `codata` | other | a fundamental physical constant of CODATA by NIST's short name, case kept: `mp` (the proton mass), `c`, `Kj`; NIST's page `physics.nist.gov/cgi-bin/cuu/Value?mp` read; Wikidata's P1645 |
| `uniprot`, `ncbi.gene`, `ensembl`, `pdb` | biology | upper case; Ensembl without its version (`ENS...` ids only: Ensembl Genomes' are their communities'); a PDB code of four characters, or its extended form `pdb_00004hhb`, kept lower case |
| `ncbi.taxon`, `inaturalist` | taxonomy | the number (`txid` read for NCBI) |
| `gbif.occurrence` | taxonomy | the number of a GBIF occurrence (`5007681734`), its page `gbif.org/occurrence/` read |
| `inaturalist.observation` | taxonomy | the number of an iNaturalist observation (`332912788`), its page `inaturalist.org/observations/` read; a GBIF occurrence published by iNaturalist carries it |
| `worms` | taxonomy | a WoRMS AphiaID (`137094`, Delphinus delphis), the taxon OBIS keys its records by; `aphia:`, the LSID `urn:lsid:marinespecies.org:taxname:137094` (read by its shape) and its page `marinespecies.org/aphia.php?p=taxdetails&id=` read |
| `obis.occurrence` | taxonomy | an OBIS occurrence by its UUID (`00007235-5211-4633-98bb-0f8f9ad53e0f`), lower case; its API address read (OBIS has no page for a record) |
| `itis` | taxonomy | an ITIS TSN (`180438`); `tsn:`, the LSID `urn:lsid:itis.gov:itis_tsn:180438` (read by its shape) and its report page read |
| `col` | taxonomy | a Catalogue of Life id in ChecklistBank (`4CGXP`, Panthera leo): stable from one release to the next, and the key GBIF gives the same taxon since June 2026 - never read by its shape, which a GBIF key shares; `catalogueoflife.org/data/taxon/` and `checklistbank.org/.../taxon/` read |
| `pbdb` | taxonomy | a Paleobiology Database taxon number (`54833`, Tyrannosaurus rex), `txn:54833` as its API writes it |
| `pbdb.occurrence` | taxonomy | a Paleobiology Database occurrence (a fossil in a collection), `occ:139292` |
| `ebird` | taxonomy | an eBird species code (`amerob`, American Robin), lower case; `ebird.org/species/` read |
| `ebird.checklist` | taxonomy | an eBird checklist (`S123456789`); `ebird.org/checklist/` read |
| `iucn` | taxonomy | an IUCN Red List taxon by its SIS id (`134817215`, Delphinus delphis); `iucnredlist.org/species/` read |
| `gbif` | taxonomy | the key of the Catalogue of Life eXtended Release, GBIF's taxonomy since June 2026, upper case (`4CGXP`: one to six of the digits 2 to 9 and the consonants, Wikidata's P14607); or the number of the legacy GBIF Backbone (`5219404`, P846). A key of digits alone is read as a legacy number |
| `ensembl.genomes` | biology | an Ensembl gene, transcript or protein of a genome Ensembl annotates under its community's ids, as Ensembl and Ensembl Genomes serve them on rest.ensembl.org: `FBgn0000008` (the fly, FlyBase), `WBGene00000001` (the worm), `YAL001C` (the budding yeast, SGD), `AT5G10140` (Arabidopsis, Araport), `Os01g0100100` (rice), `Zm00001eb000010` (maize), `TraesCS1A02G000100` (wheat), `SPAC1002.01` (fission yeast, PomBase), `PF3D7_0100100` (Plasmodium), `AGAP000002` (Anopheles); told by its shape for these ten genomes, each lookup checked on 2026-10-15 (`Identifier::ensemblSpecies()` names the genome: a lookup without it took 7 to 23 s, or failed), any other with its scheme (`ensembl.genomes:...`); identifiers.org's `ensembl.plant`, `ensembl.fungi`, `ensembl.metazoa`, `ensembl.protist`, `ensembl.bacteria` read |
| `reactome` | biology | a Reactome stable id, upper case, its version dropped: `R-HSA-70171` (a species' three letters, `ALL` for a small molecule), its page `reactome.org/content/detail/` read; Wikidata's P3937 |
| `string` | biology | a protein of STRING by its STRING id, as written: the NCBI taxon, a dot, the protein's id in its genome (`9606.ENSP00000269305`, `511145.b0001`); read by its shape (a taxon of three digits or more, an id with a letter) and from its page `string-db.org/network/` |
| `interpro`, `pfam` | biology | an InterPro entry `IPR000971`, a Pfam family `PF00042` (its version dropped), upper case, read by their shapes and from their pages in InterPro |
| `go`, `efo`, `mondo`, `hp` | biology | a term of the Gene Ontology, the Experimental Factor Ontology, the Mondo Disease Ontology, the Human Phenotype Ontology: its seven digits (`display()` gives `GO:0005829`, `MONDO:0011382` back); `GO:0005829` read by its prefix, the OBO short form `MONDO_0011382` (Open Targets', the GWAS Catalog's) by its shape; QuickGO's, OLS's and Monarch's pages read |
| `hgnc` | biology | a human gene of the HGNC, its number (`HGNC:4827` read, `display()` gives it back) |
| `omim` | biology | a MIM number, six digits (`OMIM:141900`, `MIM:#603903` read; `display()` gives `OMIM:141900`): a gene or a phenotype; a bare one needs its scheme |
| `orphanet` | biology | an ORPHAcode (`ORPHA:232`, `Orphanet_232` read; `display()` gives `ORPHA:232`) |
| `clinvar` | biology | a ClinVar variation by its VariationID (`15333`; `VCV000015333`, versioned or not, read by its shape; `display()` gives the VCV accession) |
| `dbsnp`, `gwas` | biology | a reference SNP `rs334`, lower case; a GWAS Catalog study `GCST000854`; both read by their shapes |
| `gaia` | astronomy | a source of Gaia DR3 by its `source_id`, a 64-bit number: `4472832130942575872`; `Gaia DR3 4472832130942575872` read by its shape; Wikidata's P13228 |
| `exoplanet` | astronomy | a planet or a system of the NASA Exoplanet Archive by the archive's name, spaces collapsed: `51 Peg b`, `Proxima Cen`; its overview page read; Wikidata's P5667 |
| `simbad`, `jpl.sbdb` | astronomy | the name, spaces collapsed: `M 31`, `433`, `1P/Halley` |
| `mpc` | astronomy | a minor planet, comet or natural satellite as the Minor Planet Center designates it, spaces collapsed: its number (`433`; `(433)` read so), else its designation (`2024 YR4`, `1P`, `C/2020 F3`); never read by its shape (JPL's are the same); its page `minorplanetcenter.net/db_search/show_object` read; Wikidata's P5736 |
| `ned` | astronomy | an object of NED by its preferred name, spaces collapsed: `Messier 031`, `3C 273`; its page `ned.ipac.caltech.edu/byname?objname=` read |
| `donki` | astronomy | a space weather event of NASA's DONKI by its activity id, its start then its kind and number: `2026-10-04T06:13:00-FLR-001`, read by its shape; no page by that id (DONKI numbers its pages itself) |
| `launchlibrary` | other | a launch of The Space Devs' Launch Library 2 by its UUID, lower case (`launch:` read); never read by its shape (OBIS's records are UUIDs too); its API record read and given |
| `usgs.event` | earth | lower case, a network's code then the event's: `us6000u24c`, `official20110311054624120_30` (the catalogues ComCat keeps, as Wikidata's P3196 names some) |
| `ghcn`, `wigos`, `nws.station` | earth | stations ([above](#stations)): `USW00094728`; `0-20000-0-07190` (`wmo:07190` read); `KNYC` |
| `usgs.site`, `coops.station`, `dwd.station` | earth | stations ([above](#stations)): `USGS-01646500` (read by its shape); `8518750`; `01420` |
| `emsc.event` | earth | an event of the EMSC's catalogue by its SeismicPortal id, the day then a number: `20261004_0000156`, read by its shape; its SeismicPortal page read |
| `wikidata` | any | `Q60235` |

`parse()` reads `scheme:value` (the schemes' names, identifiers.org's prefixes, `CHEBI:`), the
pages of each base and identifiers.org's links, and the values whose shape tells their scheme
(an InChIKey, a CAS number, `CHEMBL...`, `DTXSID...`, `UNII-...`, an Ensembl id, a Wikidata
Q-number, a WIGOS id, a Reactome stable id, a Gaia DR3 designation, an InterPro or Pfam accession,
an OBO term's short form, a VCV accession, an rs id, a GCST study, a STRING protein, an EC number written `EC 2.7.1.40`, a Materials Project id). A bare number, a UniProt accession or a PDB code needs its scheme: other values share
their shapes. An entity may carry two identifiers of one scheme (GBIF's new key and its legacy
number, a secondary accession): the `Merger` knows it by either. `Identifiers` keeps each identifier once: `get()`, `value()`, `values()`, `has()`,
`with()`, `merge()`, `toArray()` / `fromArray()` for storage.

### The methods of `Identifier`

An `Identifier` (`readonly`, `Stringable`) is a `scheme` (`Scheme`) and a normalised `value`
(`string`); its constructor is private: it is made by one of these, so that every value is valid
for its scheme.

| Method | |
|---|---|
| `Identifier::of(Scheme\|string $scheme, string $value)` | the identifier; `\InvalidArgumentException` when the scheme is unknown (`"x" is not a scheme Omnilab knows.`) or the value is not one of it (`"58-08-3" is not a valid CAS number.`) |
| `Identifier::tryOf(Scheme\|string\|null $scheme, int\|string\|null $value)` | the same, null in place of the exception (a null scheme or value included) |
| `Identifier::parse(string $input)` | `scheme:value`, a base's page, an identifiers.org link, or a value whose shape tells its scheme (above); null when nothing is recognised |
| `Identifier::scheme(string $name)` | the `Scheme` a name or prefix stands for (`chebi`, `pubchem.cid`, `ncbigene`, `CHEBI`...); null otherwise |
| `Identifier::cid()`, `chebi()`, `inchikey()`, `cas()`, `wikidata()` | shortcuts for `of()` with that scheme |
| `Identifier::ensemblSpecies(Identifier\|string $id)` | the genome (Ensembl's production name) an `ensembl.genomes` id's shape tells: `drosophila_melanogaster` for `FBgn0000008`; null otherwise |
| `key()` | `chebi:27732`: what the `Merger` and `Identifiers` key on; also `(string) $identifier` |
| `display()` | as its base writes it: `CHEBI:27732`, `CID 2519`, `GO:0005829`, `HGNC:4827`, `OMIM:141900`, `EFO:`, `MONDO:`, `ORPHA:232`, `HP:`, `VCV000015333`, `RHEA:18157`, `EC 2.7.1.40`; the value as it is for every other scheme |
| `url()` | the page of the base that holds it; null when the base has none (a DWD station, an OBIS record's has its API address) |
| `equals(Identifier $other)` | same scheme, same value |

`Identifiers` (`readonly`, iterable, countable) keeps each identifier once, in the order learnt
(a null given to it, an identifier that did not parse, is skipped):

| Method | |
|---|---|
| `new Identifiers(iterable $identifiers)`, `Identifiers::of(?Identifier ...)` | from a list |
| `get(Scheme)`, `value(Scheme)` | the first identifier of a scheme, its value; null when none |
| `all(?Scheme)`, `values(Scheme)` | every identifier, or those of one scheme; their values |
| `has(Scheme\|Identifier)` | one of that scheme, or that very identifier |
| `with(?Identifier ...)`, `merge(Identifiers)` | a copy with more (each still once) |
| `keys()` | `['chebi:27732', ...]` |
| `toArray()`, `Identifiers::fromArray()` | values by scheme (`['chebi' => ['27732']]`) for storage, and back |

### The methods of `Scheme`

`Model\Scheme` is a string-backed enum of 67 cases (the table above gives every value). Besides
`Scheme::from()` and `tryFrom()` on its values:

- `label()`: its name for a reader (`PubChem CID`, `ChEBI`, `Launch Library 2 launch`...), used
  in the messages of the exceptions;
- `domain()`: the `Domain` of the things it names; null for `wikidata`, which names anything.

## Page

`Source\Page` (`readonly`, iterable, countable) is one page of `search()`, `occurrences()`,
`products()`, `datasets()` or `jobs()`:

| Field or method | Type | |
|---|---|---|
| `items` | `list<Entity\|Product>` | the page's answers, in the order the source gives (best match first, newest first...) |
| `next` | `?string` | the cursor of the next page, to pass as the query's `cursor`; null on the last page |
| `total` | `?int` | how many the whole query matches, when the service counts them; null otherwise |
| `isLast()` | `bool` | `next` is null |
| `first()` | `Entity\|Product\|null` | the first item |
| `count()`, `foreach` | | the items of this page (not `total`) |

A cursor is the source's own: an offset, a page number, a token. Pass it back unchanged.

## The queries

All are `readonly` with a `with([...])` that gives a modified copy
(`$query->with(['cursor' => $page->next])`).

`Source\Query`, for `search()` and `datasets()`:

| Field | Type | Default | |
|---|---|---|---|
| `text` | `string` | required | what to look for |
| `domain` | `?Domain` | null | the domain the caller means: a source that spans several (Wikidata) narrows to it, a source of one domain answers an empty page for another |
| `limit` | `int` | 25 | answers a page; each source caps it |
| `cursor` | `?string` | null | the `Page::$next` of the page before |

`Source\SeriesQuery`, for `series()`:

| Field | Type | Default | |
|---|---|---|---|
| `variables` | `list<string>` | required, not empty | in the source's names |
| `place` | `Point\|BBox\|Identifier` | required | a box needs `Capability::AREA`; an identifier must be a station's, of `SeriesQuery::STATIONS` (`ghcn`, `wigos`, `nws.station`, `usgs.site`, `coops.station`, `dwd.station`) |
| `from`, `to` | `?\DateTimeInterface` | null | both ends included; a date without a time stands for its whole day; null: the source's default |
| `step` | `string` | `SeriesQuery::HOURLY` (`PT1H`) | `SeriesQuery::DAILY` (`P1D`) or another ISO 8601 duration the source knows |
| `aggregation` | `?string` | null | for a step longer than the measure: `Series::MEAN`, `MAX`, `MIN`, `SUM` |
| `timezone` | `?string` | null | the time zone the times and days are given in; null: the source's default |

Its constructor throws `\InvalidArgumentException` for no variable (`A series query names at least
one variable.`), an identifier that is no station (`A series is asked at a place or a station, not
at ...`), and a period that ends before it starts.

`Source\ProductQuery`, for `products()` and `jobs()`:

| Field | Type | Default | |
|---|---|---|---|
| `collections` | `list<string>` | `[]` | the source's collection ids (`sentinel-2-l2a`); empty: the source's default ones, or all |
| `place` | `Point\|BBox\|null` | null | a box needs `Capability::AREA` |
| `from`, `to` | `?\DateTimeInterface` | null | the period; one that ends before it starts is an `\InvalidArgumentException` |
| `maxCloudCover` | `?float` | null | percent, included; needs `Capability::CLOUD_COVER`, refused otherwise |
| `limit` | `int` | 25 | products a page; each source caps it |
| `cursor` | `?string` | null | the `Page::$next` of the page before |
| `sort` | `string` | `ProductQuery::NEWEST` | or `ProductQuery::OLDEST` |

`Source\OccurrenceQuery`, for `occurrences()`: [its fields, its filter keys](occurrences.md#the-query-sourceoccurrencequery);
its `limit` is 25 by default, its `sort` null (the source's order).

## The contracts

| Interface | Methods | |
|---|---|---|
| `Source\SourceInterface` | `getName()`, `capabilities()`, `entity()`, `search()`, `series()`, `products()`, `occurrences()`, `datasets()`, `open()` | every source ([Sources](sources.md)) |
| `Source\JobSourceInterface` (extends it) | `submit(string $dataset, array $request): Job`, `job(string $id): ?Job`, `jobs(ProductQuery $query = new ProductQuery()): Page` | a source that makes files on request ([datasets](datasets.md#asynchronous-requests-job)) |
| `Source\SourceFactoryInterface` | `getName(): string`, `create(array $options = []): SourceInterface` | what the `Registry` and the bundle take ([Writing a provider](providers.md)) |

`Source\SourceFactory` and `Source\HttpSource` are the abstract bases every `omnilab/*` package
extends; `Config` is the options a factory fills; `Source\Stac` reads the STAC APIs (above).
