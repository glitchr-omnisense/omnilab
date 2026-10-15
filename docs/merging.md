# Following cross-references, merging

## The Collector

```php
$collector = new Collector($registry, new Merger());

$caffeine = $collector->collect('pubchem', 'caffeine');            // one source, then the others
$caffeine = $collector->collect('pubchem', 'caffeine', depth: 2);  // and what those gave, in turn
$caffeine = $collector->follow($entity, 1, ['chebi', 'wikidata']); // an entity at hand, into the sources named
$found = $collector->search(new Query('caffeine'));                 // every source that searches, merged
$collector->incomplete;                                             // the sources skipped, and why
```

`collect()` asks the first source for the entity, then offers each other configured source with
`Capability::ENTITY` the entity's identifiers, one after the other, until one names a thing the
source knows. A source that cannot read a scheme says so before any call
(`NotSupportedException`): only the identifiers of the schemes it reads cost a call. A source
found nothing for is not asked again; a source that read none of the identifiers may be offered
the next round's. With `depth: 2`, the identifiers the second round brought (a ChEBI entry's
Wikidata item, say) are offered to the sources not asked yet.

PubChem → ChEBI → Wikidata is the path the family was drawn for. With the three configured,
caffeine from PubChem (as answered on 2026-10-15):

```php
$caffeine = $collector->collect('pubchem', 'caffeine');
$caffeine->sources;                       // ['pubchem', 'chebi', 'wikidata']
$caffeine->properties('molecular_weight'); // 194.19 g/mol [pubchem], 194.194 g/mol [chebi]
$caffeine->properties('monoisotopic_mass');// 194.08037557 Da [pubchem], 194.08038 Da [chebi]
$caffeine->properties('is_a');            // purine alkaloid [chebi], trimethylxanthine [chebi]
$caffeine->properties('melting_point');   // 234 °C [wikidata], 235 °C [wikidata]
$caffeine->property('density');           // 1.23 g/cm³ [wikidata]
$caffeine->identifier(Scheme::KEGG);      // C07481 (PubChem's synonyms); ChEBI and Wikidata add D00528
```

Nine calls: four of PubChem (the CID, the properties, the synonyms, the description); ChEBI is
offered the CID first, refuses it without a call, then reads the InChIKey (a search, then the
entry); Wikidata reads the CID (`haswbstatement:P662=2519`, then the item, then the label of its
class). ChEBI 2.0 gives no PubChem CID nor Wikidata item for caffeine: the InChIKey and the CID,
which PubChem gave, are what joins them. About eight seconds, PubChem's throttle and Wikidata's
answer (260 kB) for most of it.

Two paths more, through biology and taxonomy (answered on 2026-10-15). **A protein, from UniProt
into NCBI Gene and Wikidata** - hemoglobin beta, chosen for an entry that does not move (reviewed,
annotation score 5, its sequence unchanged since 2007):

```php
$hbb = $collector->collect('uniprot', 'P68871', 1, ['ncbi', 'wikidata']);
$hbb->sources;                            // ['uniprot', 'ncbi', 'wikidata']
$hbb->identifiers()->keys();              // uniprot:P68871, ncbi.gene:3043, ensembl:ENSG00000244734, wikidata:Q424422
$hbb->properties('gene');                 // HBB [uniprot], HBB [ncbi]
$hbb->property('mass');                   // 15998 Da [uniprot]
$hbb->property('map_location');           // 11p15.4 [ncbi]
$hbb->property('genomic_location');       // 11:5225464-5227071 (minus strand), NC_000011.10, GRCh38.p14 [ncbi]
$hbb->related('organism')->keys();        // ncbi.taxon:9606: related, not followed
$hbb->related('ligand')->keys();          // chebi:58248, chebi:60344 (2,3-bisphosphoglycerate, heme b)
```

Five calls, about five seconds: UniProt's entry (its fields only, 59 kB); NCBI is offered the
accession, refuses it without a call, then reads GeneID 3043 (one Datasets report); Wikidata reads
the accession (`haswbstatement:P352=P68871`: Q424422, the protein's item - the gene has its own,
Q14905542), then the item (435 kB), then the label of its class. The organism, the structures and
the ligands are not offered to anyone: they are other things than the protein, kept in `related`.

**A species, from GBIF into NCBI Taxonomy and Wikidata** - *Panthera leo*:

```php
$lion = $collector->collect('gbif', 'Panthera leo', 1, ['ncbi', 'wikidata']);
$lion->sources;                           // ['gbif', 'ncbi', 'wikidata']
$lion->identifiers()->keys();             // gbif:4CGXP, gbif:5219404, ncbi.taxon:9689, wikidata:Q140, inaturalist:41964
$lion->properties('taxon_name');          // Panthera leo [gbif], Panthera leo [ncbi], Panthera leo [wikidata]
$lion->properties('kingdom');             // Animalia [gbif], Metazoa [ncbi]: each base's own
$lion->property('iucn_status');           // Vulnerable (VU) [gbif]
$lion->properties('common_name');         // African Lion [gbif], lion [ncbi]
$lion->related('parent')->keys();         // gbif:6DBT, ncbi.taxon:9688: Panthera, in each base
```

Nine calls, about five seconds: three of GBIF (its match, the checklist's record, the ENA taxonomy's
usage that gives the NCBI id); NCBI refuses both GBIF keys without a call and reads 9689 (its report,
then its links, which give the legacy GBIF number back); Wikidata is offered GBIF's new key first
(`haswbstatement:P14607=4CGXP`: no item carries it yet), then the legacy number (`P846=5219404`:
Q140), then the item and the label of its class. The iNaturalist id comes from Wikidata's item.

**An earthquake, from Wikidata into the USGS catalogue** - the 2025 Myanmar earthquake, Wikidata's
Q133574091, whose P3196 is the USGS id `us7000pn9s` (answered on 2026-10-15). Wikidata gives the
day (P585) and its own epicentre; the USGS the second and its epicentre: preferring the USGS for
`time` and `place` takes those.

```php
$collector = new Collector($registry, new Merger(['time' => ['usgs'], 'place' => ['usgs']]));
$quake = $collector->collect('wikidata', 'Q133574091', 1, ['usgs']);
$quake->sources;                          // ['wikidata', 'usgs']
$quake->identifiers()->keys();            // wikidata:Q133574091, usgs.event:us7000pn9s, usgs.event:usauto7000pn9s, usgs.event:pt25087002, usgs.event:at00sttln4
$quake->time;                             // 2025-03-28 06:20:52 UTC [usgs] (Wikidata: 2025-03-28)
$quake->place;                            // 22.011, 95.9363 [usgs] (Wikidata: 22.013, 95.922)
$quake->property('moment_magnitude');     // 7.7 [wikidata]
$quake->property('magnitude');            // 7.7 ± 0.028 [usgs], magnitude_type mww
$quake->property('depth');                // 10 ± 1.719 km [usgs]
$quake->value('alert');                   // red (PAGER) [usgs]
$quake->value('felt');                    // 2545 "Did You Feel It?" reports [usgs]; mmi 9.953, cdi 9.1
```

Three calls, about two seconds: Wikidata's item and the label of its class (earthquake), then the
USGS's event (`query?format=geojson&eventid=us7000pn9s`), whose other networks' ids join the
identifiers. Starting from the USGS (`collect('usgs', 'us7000pn9s', 1, ['wikidata'])`) costs four:
the event, then Wikidata's `haswbstatement:P3196=us7000pn9s`, the item and its class. The great
historical earthquakes carry in P3196 the id of a catalogue the USGS keeps rather than its own:
the 2011 Tohoku earthquake (Q36204) has `official20110311054624120_30`, which the USGS answers
with its M 9.1 (mww), depth 29 km and 05:46:24 UTC, joined in three calls as well - Wikidata adding
its Richter (9) and moment (9.1) magnitudes.

A source that is down or rate limited is skipped and named in `$incomplete`; the others still
answer. Do not cache an answer while `$incomplete` is not empty.

## The Merger

```php
$merger = new Merger(['default' => ['pubchem', 'chebi'], 'description' => ['chebi'], 'molecular_weight' => ['pubchem']]);
$entities = $merger->merge($pubchemPage, $chebiList);   // lists or pages: one entity per thing
$one = $merger->combine($a, $b, $c);                     // several that describe one thing, no check
$merger->same($a, $b);
```

**Two entities are the same thing** when they share an identifier, unless one carries, in the
scheme of the other's own id, a different value: two PubChem compounds with two CIDs stay two,
whatever CAS number they share - a salt and its base often do.

**Each field from its preferred source**: `label`, `kind`, `description`, `url`, `image`, `time`
and `place` come from the first entity, in the order of the sources' preference for that field,
that has one; `domain` from the first that is not `other`; `id` and `source` from the first in the
`default` order. A source not listed comes after those listed, in the order the entities were
given.

**Every property kept, with its source**: a key given by two sources keeps both properties, the
one of the source preferred for that key (or the `default` order) first: `property($key)` gives
it, `properties($key)` both. A property without a source takes its entity's.

**United**: identifiers (each once, in the `default` order of the sources), synonyms (each once
whatever its case, the label left out), sources.
