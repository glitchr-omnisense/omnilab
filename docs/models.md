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

## Quantity

A number, its unit as the source spells it (`g/mol`, `Å²`, `°C`, `mm`), and an uncertainty when
given. No conversion is made; `scaled($factor, $unit)` gives another unit when the caller knows
the factor. `(string) new Quantity(194.19, 'g/mol')` is `194.19 g/mol`.

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

`SeriesQuery(variables, place, from, to, step, aggregation, timezone)`: both ends of the period
included, a date without a time standing for its whole day; the time zone the times and the days
are given in (`auto` is the point's own, at Open-Meteo). The variables are the source's names: the
weather's `temperature_2m`, the air's `pm2_5`, `nitrogen_dioxide`, `european_aqi` (Open-Meteo), the
earthquakes' `count` (the USGS).

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
| `uniprot`, `ncbi.gene`, `ensembl`, `pdb` | biology | upper case; Ensembl without its version (`ENS...` ids only: Ensembl Genomes' are their communities'); a PDB code of four characters, or its extended form `pdb_00004hhb`, kept lower case |
| `ncbi.taxon`, `inaturalist` | taxonomy | the number (`txid` read for NCBI) |
| `gbif.occurrence` | taxonomy | the number of a GBIF occurrence (`5007681734`), its page `gbif.org/occurrence/` read |
| `inaturalist.observation` | taxonomy | the number of an iNaturalist observation (`332912788`), its page `inaturalist.org/observations/` read; a GBIF occurrence published by iNaturalist carries it |
| `gbif` | taxonomy | the key of the Catalogue of Life eXtended Release, GBIF's taxonomy since June 2026, upper case (`4CGXP`: one to six of the digits 2 to 9 and the consonants, Wikidata's P14607); or the number of the legacy GBIF Backbone (`5219404`, P846). A key of digits alone is read as a legacy number |
| `simbad`, `jpl.sbdb` | astronomy | the name, spaces collapsed: `M 31`, `433`, `1P/Halley` |
| `usgs.event` | earth | lower case, a network's code then the event's: `us6000u24c`, `official20110311054624120_30` (the catalogues ComCat keeps, as Wikidata's P3196 names some) |
| `wikidata` | any | `Q60235` |

`parse()` reads `scheme:value` (the schemes' names, identifiers.org's prefixes, `CHEBI:`), the
pages of each base and identifiers.org's links, and the values whose shape tells their scheme
(an InChIKey, a CAS number, `CHEMBL...`, `DTXSID...`, `UNII-...`, an Ensembl id, a Wikidata
Q-number). A bare number, a UniProt accession or a PDB code needs its scheme: other values share
their shapes. An entity may carry two identifiers of one scheme (GBIF's new key and its legacy
number, a secondary accession): the `Merger` knows it by either. `Identifiers` keeps each identifier once: `get()`, `value()`, `values()`, `has()`,
`with()`, `merge()`, `toArray()` / `fromArray()` for storage.
