# Sources

Every source implements `Omnilab\Source\SourceInterface`:

| Method | Answers |
|---|---|
| `getName()` | its factory's name: `pubchem`, `chebi`, `wikidata`, `open-meteo`, `copernicus`... |
| `capabilities()` | a list of `Capability`: what it can be asked, what its answers hold |
| `entity($id)` | an `Entity`, or null |
| `search(Query)` | a `Page` of `Entity`, the best match first; `$page->next` is the next cursor |
| `series(SeriesQuery)` | a list of `Series`, one per variable asked, in the order asked |
| `products(ProductQuery)` | a `Page` of `Product`, newest first by default |

What a source does not do throws `NotSupportedException`, before any call. `HttpSource`, the base
of the sources over HTTP, throws it for every operation a source does not override.

## Capabilities

| Capability | Says |
|---|---|
| `entity` | `entity()` reads one thing by an identifier (or a name, where the source reads names) |
| `search` | `search()` finds things by text |
| `series` | `series()` serves a variable at a place over a time |
| `products` | `products()` lists catalogued products over a place and a time |
| `structure` | an entity's `image` is its structure (a 2D depiction of a molecule) |
| `cross_references` | an entity carries identifiers of other bases: the `Collector` can follow them |
| `forecast` | `series()` answers ahead of now |
| `historical` | `series()` answers for the past: observations, an archive, a reanalysis |
| `area` | `series()` or `products()` take a `BBox`; without it, a box is refused rather than answered for its centre |
| `cloud_cover` | `products()` applies `ProductQuery::$maxCloudCover`; without it, the query is refused rather than answered unfiltered |

## What each one does

| | pubchem | chebi | wikidata | open-meteo | copernicus |
|---|---|---|---|---|---|
| Shape | entity | entity | entity | series | product |
| `entity()` by | CID, name, InChIKey, InChI, CAS, SMILES, ChEBI, ChEMBL, Wikidata, UNII, DSSTox | ChEBI id (a secondary one too), InChIKey, CAS, KEGG, name | Q-number, label, and the identifier of any scheme below its statements carry (but InChI) | - | - |
| `search()` | the exact name first, then the names that hold the text as a word | ChEBI's search: an exact match first | by label or alias; with a domain, by text among the items that carry a statement of it | - | - |
| `series()` | - | - | - | any Open-Meteo variable at a `Point`: hourly (`PT1H`), 15 minutes (`PT15M`), daily (`P1D`, with `mean`, `max`, `min`, `sum`) | - |
| `products()` | - | - | - | - | any collection of the catalogue, at a `Point` or over a `BBox`, a period, a cloud cover maximum |
| Units | from PubChem's documentation (g/mol, Da, Å²) | g/mol (average mass), Da | the unit item's symbol (Da, °C, g/cm³...) | as Open-Meteo answers (°C, mm, km/h...) | as the catalogue names its properties |
| Cross-references | InChIKey, InChI, CAS, ChEBI, ChEMBL, Wikidata, UNII, DSSTox, KEGG | secondary ChEBI ids, InChIKey, InChI, CAS, KEGG (and ChEMBL, PubChem, Wikidata, UniProt where ChEBI keeps them) | PubChem CID, ChEBI, CAS, InChIKey, InChI, ChEMBL, UNII, DSSTox, KEGG, UniProt, NCBI Gene, Ensembl, NCBI Taxonomy, GBIF, iNaturalist, SIMBAD, JPL SBDB | - | - |
| Paging (cursor) | offset over the CIDs found | ChEBI's page number | the search's offset | - | the next link's token |
| Per page, at most | 100 | 100 | 50 | - | 100 |

| | uniprot | ncbi |
|---|---|---|
| Shape | entity | entity |
| `entity()` by | accession (a merged one gives its entry), entry name (`HBB_HUMAN`) | GeneID; NCBI taxon id (`txid9689`); a taxon's scientific or common name |
| `search()` | UniProt's query syntax, page by the `Link` header's cursor | Entrez's syntax (`esearch`): NCBI Gene, or NCBI Taxonomy for `Domain::TAXONOMY` |
| Units | aa, Da | - |
| Cross-references | NCBI Gene, Ensembl gene | a gene: UniProt (Swiss-Prot), Ensembl; a taxon: GBIF (from its links) |
| Related | `organism` (NCBI taxon), `structure` (PDB, 20 by default), `ligand`, `cofactor` (ChEBI) | a gene's `organism`, a taxon's `parent` |
| Paging (cursor) | UniProt's cursor | the search's offset |
| Per page, at most | 500 | 100 |

## What the services do not allow

- **PubChem**: five calls a second and 400 a minute at most (the throttle leaves 0.2 s between
  two); a compound costs up to four calls; synonyms are the depositors', cross-references are read
  from them (the first of each scheme, in PubChem's order); experimental properties (melting
  point...) are in PUG-View, not read here; a search by word ranks nothing beyond the exact name.
- **ChEBI**: one call for an entry by its ChEBI id, two by anything else (its search first); no
  PubChem CID nor Wikidata item among caffeine's cross-references (the Wikidata source finds the
  item by the ChEBI id); the ontology's relations give names, not ids; the search answers 404 for
  nothing found, and does not answer a page past the last one (the source never asks it); the
  SOAP service of ChEBI 1 is gone.
- **Wikidata**: a User-Agent that names the caller is required (403 otherwise); 200 calls a
  minute for an unauthenticated client (Wikimedia's limits, new in 2026), in series; an item costs
  two or three calls and its answer is large (caffeine's, 260 kB); an identifier two items carry
  names neither; PDB structures (P638) are not taken for cross-references; GBIF's 2026 taxon ids
  (P14607) do not fit the `gbif` scheme, the older keys (P846) are read; the search index follows
  edits after a delay.
- **Open-Meteo**: a point only, not a box; the forecast API answers from 92 days ago to 16 days
  ahead, the archive from 1940 to about five days ago; free for non-commercial use only, within
  10 000 calls a day; the point of the model's grid answers, not the exact one asked.
- **Copernicus Data Space**: searching is open, downloading an asset needs a CDSE access token
  (out of this package); the catalogue does not count the matches; at most 100 Sentinel-2 items a
  page without fields selection; a radar collection has no cloud cover, so a cloud filter matches
  nothing there.

- **UniProt**: no rate published (only the `stream` endpoint, not used, answers 429); an entry
  whole is large (hemoglobin beta: 342 kB), so each call names its fields; asked for `gzip` alone,
  UniProt compresses the body twice - the source asks for `identity`; an accession merged into
  another is answered 303 (a client that follows it gets the entry whole, its fields dropped); no
  Wikidata item among its cross-references, no secondary accessions among the fields.
- **NCBI**: three calls a second for E-utilities, five for Datasets, ten for both with an API key;
  `tool` and `email` asked of every E-utilities client; Datasets has no text search (E-utilities'
  `esearch` does it, Datasets then reads the page in one call); a taxon's links name GBIF, EOL and
  Wikipedia, not Wikidata nor iNaturalist; GBIF's page there is a legacy key.

## Throttling and politeness

| Source | Default `throttle` | Contact |
|---|---|---|
| pubchem | 0.2 s (PubChem's five a second) | `mailto` in the User-Agent |
| chebi | none (no rate published) | `mailto` in the User-Agent |
| wikidata | 0.3 s (Wikimedia's 200 a minute) | a User-Agent with `mailto` or the project's URL: required |
| open-meteo | none | User-Agent; `api_key` for a commercial plan |
| copernicus | none | User-Agent |
| uniprot | none (no rate published) | `mailto` in the User-Agent |
| ncbi | 0.34 s without a key, 0.1 s with one (`auto`) | `tool` and `email` (from `mailto`) on E-utilities; `api_key` |

Every factory takes `mailto`, `user_agent`, `throttle` (seconds between two calls of one source
instance) and `base_uri`.

## Writing a source

A source is a package `omnilab/<name>` whose factory extends `Source\SourceFactory` (its name, its
options and their defaults in `populate()`, the source in `build()`) and whose source extends
`Source\HttpSource`, overriding the operations it does and declaring them in `capabilities()`. It
requires `glitchr/omnilab` and `symfony/http-client`, and accepts the client given to its factory.
Its identifiers are those of `Model\Scheme`: a base without a scheme yet adds one to the core
first. What a source reads it reads from an `Identifier` of one of its schemes, and it throws
`NotSupportedException::identifier()` for any other before calling anything: the `Collector`
relies on it.
