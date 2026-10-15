# Decisions

What the contract settled, and why. Each was taken with the family's author; a change to one is a
change of the contract.

## Identifiers name the thing; related things are another field (2026-10-15)

`Entity::$identifiers` holds the other names of **the same thing** in other bases: a compound's
CAS number and ChEBI id, a protein's NCBI gene record, a taxon's legacy GBIF number. The
`Collector` follows them into the other sources; the `Merger` takes two entities that share one
for one thing.

`Entity::$related` (`array<string, Identifiers>`, by relation) holds **other things** an entity is
tied to: a protein's `organism` (`ncbi.taxon:9606`), its `structure`s (`pdb:1A3N`), the `ligand`s
and `cofactor`s it binds (`chebi:60344`), a molecule's `target`s, a taxon's `parent`, a synonym's
`accepted` taxon, an occurrence's `taxon`. The `Collector` does not follow them; the `Merger`
joins nothing by them, and unites them when it merges.

**Why**: UniProt gives a protein's organism - human hemoglobin beta (P68871), the NCBI taxon
9606. Kept among the protein's identifiers, it would have sent the `Collector` from the protein
into NCBI Taxonomy's *Homo sapiens*, and the `Merger` would have fused the two because they shared
an identifier: a protein and a species one entity, the species' lineage among the protein's
properties - and every human protein one with every other. An identifier says "is", a relation "is tied to"; one list could not hold both.

**How to apply**: a new source puts in `identifiers` only what names the very thing (when in
doubt, ask: would two entities that share it be one?), and the rest in `related`, under a relation
that says what it is (`organism`, `parent`, `target`...), reusing a relation's name before making
one.

**Applied since**: omnilab/pdb keeps an entry's chains' proteins (`protein`), their organisms and
its ligands as related things - a structure is not its protein, and two structures of hemoglobin
are not one -, so a PDB entry carries no identifier another source shares and the `Collector` takes
it alone. omnilab/ensembl relates a gene's transcripts and a transcript's protein (other things),
and keeps among a gene's identifiers its NCBI Gene record and its Swiss-Prot entries, as NCBI and
UniProt already joined a gene and its protein. A GBIF occurrence published by iNaturalist carries
the observation's number among its identifiers: it is that very record (`inaturalist.observation`).

## A station in place of a point (2026-10-15)

`SeriesQuery::$place` is a `Point`, a `BBox` or an `Identifier`: a station's (`ghcn`, `wigos`,
`nws.station`), `Capability::STATION` saying that a source reads them. A station is an `Entity`
too (kind `station`, its place and elevation, the period its records cover), which `entity()`
answers.

**Why**: a series at a point is the source's choice of where - Open-Meteo's grid point, NCEI's
nearest station that has the variables (around Strasbourg, Entzheim rather than Kehl, nearer but
without TMAX since 2003). A climatologist asks for a station's records by its id, and omnilab/noaa
did it by a method of its own, `seriesAt()`, outside the contract. A station is a place with an
identity: what an `Identifier` is for, not a field of its own.

**Why these schemes**: each base's own id, as everywhere in the contract - GHCN's (NCEI's stations
of the whole world, 133,039 on 2026-10-15), the WMO's WIGOS id (the register every national
service reports to, the one Wikidata keeps, P4136; the five-digit index of old is its
`0-20000-0-` form) and the NWS's (an ICAO airport, a buoy, a network's code: its own list).
GHCN's and the NWS's shapes are any code's - eleven or four characters -, so a bare value is not
read as one: a name such as "CAFFEINATED" would have been a station.

**Why not a field `station`**: a query that named a point and a station would say two places;
`place` says one, of three kinds, and a source refuses the kinds it does not read before any call,
as it refuses a box without `Capability::AREA`.

**Applied**: omnilab/noaa reads `ghcn` stations through NCEI and `nws.station` through the NWS,
and answers their records; `seriesAt()` stays, deprecated, through `series()`. Open-Meteo and the
USGS refuse a station.

## A fourth shape: occurrences (2026-10-15)

`SourceInterface::occurrences(OccurrenceQuery): Page<Entity>`, `Capability::OCCURRENCES` - the
things found or happened at a place in a period ([occurrences](occurrences.md)). Two sources
needed it and neither fitted: GBIF's occurrences (left out of omnilab/gbif at first) and the
USGS's earthquakes of a region, which omnilab/usgs gave by a method of its own, `events()`,
outside the contract.

**Why not one of the three shapes**:

- not `search()`: its `Query` is a text; a place and a period are not a text, and a search is
  "which things are named so", not "which happened there";
- not a `Series`: a series is one variable at one place over time; each occurrence has its own
  place, its own properties and its own identity (the USGS gives each event's count or largest
  magnitude per day as series, and keeps doing so - another question);
- not a `Product`: a product is a catalogued file (its footprint, its assets to download, the
  catalogue's raw properties); an occurrence is a thing, with an identifier the `Collector` can
  follow (an earthquake's into Wikidata), properties with their units and sources, and related
  things (its taxon) - what an `Entity` holds.

So the answer is the `Entity` already known, with its `time` and its `place` (the fields were
made for "what happens somewhere at a moment"), and only the question is new.

**Why "occurrences"**: the word says "was found, or happened, at a place and a time" for either
kind of thing - Darwin Core's *occurrence*, the record of the largest of these services (GBIF:
3,981,411,127 on 2026-10-15), and the seismologists' "earthquake occurrence". *Events* was the USGS's word, but
in Darwin Core an Event is a sampling event, a class of its own beside the Occurrence, and in the
applications that call omnilab an event is a dispatched object; *observations* leaves out the
museum specimens and the instruments' detections; *records* and *features* say nothing.

**Why these choices**:

- **The place is a `BBox`, or a `Point` and a radius** - the two ways the services take it
  (GBIF's `geoDistance` and its ranges, the FDSN's `maxradiuskm` and its box); the radius belongs
  to the query, the source's option is only its default.
- **What only a domain filters on is a filter by key**, not a typed field: a taxon, a country, a
  magnitude are each one domain's, and a typed field per domain would grow the core with every
  source - the contract is shapes, not domains. The keys several sources share are constants of
  the query (`TAXON`, `COUNTRY`, `KIND`, `MIN_MAGNITUDE`, `MAX_MAGNITUDE`), and a source refuses
  a key it does not read before any call (`HttpSource::filters()`), as it refuses a box without
  `Capability::AREA`: never an answer silently unfiltered.
- **A `Page`**, as `search()` and `products()`: the services page (GBIF by offset, 300 a page,
  100,000 deep; the USGS by offset, 20,000 a page), and `total` says the count when the service
  gives it (GBIF does, the USGS would need a call more).
- **Sort is a string**: `NEWEST`, `OLDEST`, or a key of the source (`magnitude`); null leaves the
  source's order, and a source that cannot sort as asked refuses (GBIF sorts nothing).
- **Every entity of a page has its time and place**: a source asks for them or leaves out what
  lacks them (GBIF: `hasCoordinate`, a date range), so a caller never checks.
- **Not in the `Collector`**: a page has no identity to follow and two sources' pages hold
  different things; merging by nearness would guess. One occurrence is an entity like any, which
  `follow()` takes ([occurrences](occurrences.md#not-in-the-collector)).
- **`events()` of omnilab/usgs stays, deprecated**: it answers what it answered, through
  `occurrences()`.
