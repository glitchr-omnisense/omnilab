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

**A gene, from NCBI Gene or UniProt into Ensembl, and back** - human HBB (answered on 2026-10-15):

```php
$hbb = $collector->collect('ncbi', '3043', 1, ['ensembl']);      // or collect('uniprot', 'P68871', 1, ['ensembl'])
$hbb->sources;                            // ['ncbi', 'ensembl']
$hbb->identifiers()->keys();              // ncbi.gene:3043, uniprot:P68871, ensembl:ENSG00000244734
$hbb->properties('genomic_location');     // 11:5225464-5227071 (minus strand), NC_000011.10, GRCh38.p14 [ncbi],
                                          // 11:5284832-5286439 (minus strand), NC_060935.1, T2T-CHM13v2.0 [ncbi],
                                          // 11:5225464-5229395 (minus strand), GRCh38 [ensembl]
$hbb->properties('biotype');              // protein_coding [ensembl]

$gene = $collector->collect('ensembl', 'ENSG00000244734', 1, ['ncbi', 'uniprot', 'wikidata']);
$gene->sources;                           // ['ensembl', 'ncbi', 'uniprot', 'wikidata']
$gene->identifier(Scheme::WIKIDATA);      // Q14905542: the HBB gene's item, by the Ensembl id (P594)
```

NCBI Gene and UniProt keep the gene's Ensembl id among their cross-references; Ensembl is offered it
and reads the gene in four calls (its record, its species, its cross-references, its Swiss-Prot
entries). Back, Ensembl gives NCBI Gene its GeneID (3043) and UniProt its Swiss-Prot entry
(P68871); Wikidata finds the gene's own item by P594 (the protein has another, Q424422, which the
path from UniProt reaches by P352). Ensembl's transcripts and proteins are other things: related.

**A structure, taken alone** - the PDB entries of hemoglobin beta. A structure is not its protein:
UniProt keeps a protein's structures as related things, and a PDB entry has no identifier another
source shares, so nothing joins them:

```php
$hbb = $registry->get('uniprot')->entity('P68871');
$hbb->related('structure')->keys();       // pdb:1A00, pdb:1A01, pdb:1A0U... (20 kept by omnilab/uniprot)
$entry = $collector->collect('pdb', '4HHB');
$entry->sources;                          // ['pdb']: one call, nothing to follow
$entry->related('protein')->keys();       // uniprot:P69905, uniprot:P68871: its chains
$entry->related('ligand')->keys();        // inchikey:KABFMIBPWCXCRK-RGGAHWMASA-L (heme), chebi:18367 (phosphate)
$collector->collect('uniprot', $entry->related('protein')->get(Scheme::UNIPROT));   // back to a chain's protein
```

**A species, from GBIF or Wikidata into iNaturalist, and back** - *Panthera leo* again. iNaturalist
gives no other base's id; Wikidata's item carries the iNaturalist taxon (P3151 = 41964):

```php
$lion = $collector->collect('gbif', '4CGXP', 2, ['ncbi', 'wikidata', 'inaturalist']);
$lion->sources;                           // ['gbif', 'ncbi', 'wikidata', 'inaturalist']
$lion->identifiers()->keys();             // gbif:4CGXP, gbif:5219404, ncbi.taxon:9689, wikidata:Q140, inaturalist:41964
$lion->properties('iucn_status');         // Vulnerable (VU) [gbif], Vulnerable (VU) [inaturalist]
$lion->properties('common_name');         // African Lion [gbif], lion [ncbi], Lion [inaturalist]
```

Nine calls, about six seconds: the first round as above (GBIF, NCBI, Wikidata), the second offers
what Wikidata found to iNaturalist, which reads 41964 in one call. From Wikidata at depth 1
(`collect('wikidata', 'Q140', 1, ['inaturalist'])`) it takes three calls; from iNaturalist at depth 2
(`collect('inaturalist', '41964', 2, ['wikidata', 'gbif', 'ncbi'])`) nine: Wikidata finds the item by
P3151, then GBIF and NCBI read the ids it carries.

**An occurrence, followed** - GBIF's occurrences published by iNaturalist name the observation they
are (their `occurrenceID` is its page), which omnilab/gbif reads as an identifier:

```php
$occurrence = $registry->get('gbif')->entity('gbif.occurrence:5007681734');
$occurrence->identifiers->keys();         // inaturalist.observation:257284120
$both = $collector->follow($occurrence, 1, ['inaturalist']);
$both->sources;                           // ['gbif', 'inaturalist']: one call more
$both->value('quality_grade');            // research [inaturalist], beside GBIF's dataset, licence and recorder
```

A page of occurrences is not followed (see [occurrences](occurrences.md#not-in-the-collector)); one
occurrence is an entity like any.

**A fly's gene, from Ensembl into NCBI Gene** - Ensembl serves the fly under FlyBase's ids (the
`ensembl.genomes` scheme), and gives the NCBI Gene record among a gene's cross-references:
`collect('ensembl', 'FBgn0000008', 1, ['ncbi'])` - the fly's `a`, GeneID 43852, `arc` from each,
21.5 s on the evening of 2026-10-15 (Ensembl slow that day).

**A planet, from Wikidata into SIMBAD and the NASA Exoplanet Archive** - Wikidata's item for 51 Peg
b (Q242309) carries its SIMBAD identifier (P3083, `* 51 Peg b`) and its name at the archive (P5667,
`51 Peg b`, the `exoplanet` scheme):

```php
$planet = $collector->collect('wikidata', 'Q242309', 1, ['simbad', 'exoplanets']);
$planet->sources;                         // ['wikidata', 'simbad', 'exoplanets']
$planet->identifiers()->keys();           // wikidata:Q242309, simbad:* 51 Peg b, exoplanet:51 Peg b, simbad:51 Peg b
$planet->properties('orbital_period');    // 4.2307969 ± 0.00000755 d [wikidata], 4.2307966 ± 0.0000027 days [exoplanets]
$planet->properties('parallax');          // 64.4048 ± 0.0771 mas [simbad], 64.6488 ± 0.123201 mas [exoplanets]
$planet->related('host');                 // 51 Peg by its designations, gaia:2835207319109249920 among them
```

(3.9 s on the evening of 2026-10-15)

**A star, from SIMBAD into Gaia DR3** - SIMBAD gives a star's Gaia DR3 designation among its
identifiers (`simbad:Gaia DR3 4472832130942575872` for Barnard's star), which omnilab/gaia reads; the
Gaia entity carries it beside its own `gaia:4472832130942575872`, so the two merge:
`collect('simbad', "Barnard's star", 1, ['gaia'])` took two calls in 8.7 s on 2026-10-15
(SIMBAD's query, Gaia's for that source_id; the parallax 546.98 mas of each). Wikidata reaches Gaia
directly by P13228 (`collect('wikidata', 'Q14268', 1, ['gaia'])`: the item carries
`gaia:4472832130942575872`). That evening the ESA Gaia Archive answered its maintenance page (an
`UnavailableException`): the `Collector` named gaia in `$incomplete` and gave SIMBAD's answer alone.

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
