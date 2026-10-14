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
| `identifiers` | `Identifiers`: its own, then the cross-references to other bases; `identifiers()` adds `id` |
| `url`, `image` | its page; its picture (a structure, where the source has `Capability::STRUCTURE`) |
| `time`, `place` | for what happens somewhere at a moment (an earthquake) or sits somewhere (a station): a `Point` or a `BBox` |
| `source`, `sources` | the source of the record; after a merge, every source merged |

`property($key)` gives the preferred source's property of a key, `properties($key)` every source's,
`value($key)` its value, `identifier(Scheme::CAS)` the first value of a scheme, `keys()` the keys
known.

Property keys are lower case, snake case, and mean the same from one source to the next:
`molecular_formula`, `molecular_weight`, `exact_mass`, `monoisotopic_mass`, `iupac_name`,
`smiles`, `inchi`, `inchikey`, `xlogp`, `tpsa`, `complexity`, `charge`, `h_bond_donors`,
`h_bond_acceptors`, `rotatable_bonds`, `heavy_atoms` (PubChem); a new source reuses a key that
exists before it makes one.

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
are given in (`auto` is the point's own, at Open-Meteo).

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
Identifier::of(Scheme::GBIF, '2435099')->url();                      // https://www.gbif.org/species/2435099
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
| `uniprot`, `ncbi.gene`, `ensembl`, `pdb` | biology | upper case; Ensembl without its version |
| `ncbi.taxon`, `gbif`, `inaturalist` | taxonomy | the number |
| `simbad`, `jpl.sbdb` | astronomy | the name, spaces collapsed: `M 31`, `433`, `1P/Halley` |
| `usgs.event` | earth | lower case: `us6000u24c` |
| `wikidata` | any | `Q60235` |

`parse()` reads `scheme:value` (the schemes' names, identifiers.org's prefixes, `CHEBI:`), the
pages of each base and identifiers.org's links, and the values whose shape tells their scheme
(an InChIKey, a CAS number, `CHEMBL...`, `DTXSID...`, `UNII-...`, an Ensembl id, a Wikidata
Q-number). A bare number, a UniProt accession or a PDB code needs its scheme: other values share
their shapes. `Identifiers` keeps each identifier once: `get()`, `value()`, `values()`, `has()`,
`with()`, `merge()`, `toArray()` / `fromArray()` for storage.
