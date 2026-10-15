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

**A pathway, from Wikidata into Reactome** - Wikidata's item for glycolysis (Q45317172) carries its
Reactome ID (P3937, `R-HSA-70171`):

```php
$glycolysis = $collector->collect('wikidata', 'Q45317172', 1, ['reactome']);
$glycolysis->sources;                          // ['wikidata', 'reactome']
$glycolysis->value('go_biological_process');   // canonical glycolysis (GO:0061621) [reactome]
$glycolysis->related('protein');               // 79 UniProt accessions, GCK (P35557) among them
```

(4 calls in 2.0 s on 2026-10-15: Wikidata's item and its classes' labels, Reactome's event and its
participants.) A protein's pathways are not a `Collector` path - a protein is not its pathways -
but omnilab/reactome's `pathways()` gives them: hemoglobin beta (UniProt P68871) takes part in ten
pathways and 28 reactions in Reactome's release 97.

A source that is down or rate limited is skipped and named in `$incomplete`; the others still
answer. Do not cache an answer while `$incomplete` is not empty.

**A reaction, from KEGG into Rhea, and back** - KEGG's reaction R00200 (pyruvate kinase) carries
`rhea:18160`, the bidirectional id of Rhea's reaction 18157, and Rhea's 18157 carries
`kegg.reaction:R00200` and its three other ids: the `Merger` knows it by any of them.

```php
$collector->collect('rhea', '18157', 1, ['kegg']);     // Rhea, then KEGG by R00200
$collector->collect('kegg', 'R00200', 1, ['rhea']);     // KEGG, then Rhea by 18160 (its master 18157)
```

Not walked live: KEGG's API is for academic users only, and omnilab/kegg is not tested live
against it. Rhea's half was answered on 2026-10-15 (one SPARQL query, one UniProt search).

**A protein, from UniProt into STRING, InterPro and QuickGO** - walked against the real services
on 2026-10-15. A protein's UniProt accession takes it into the
sources that read accessions - STRING, InterPro, QuickGO - each adding what it knows of that
protein: its interaction partners, its domains, its GO terms, all as related things:

```php
$collector->collect('uniprot', 'P68871', 1, ['string']);     // 3 calls, 3.1 s: ten partners, HBA2 first (0.999)
$collector->collect('uniprot', 'P68871', 1, ['interpro']);   // 3 calls, 3.3 to 4.1 s: domain IPR000971 and PF00042, two families
$collector->collect('uniprot', 'P68871', 1, ['quickgo']);    // 2 calls, 5.1 s: 8 functions, 11 processes, 9 components
```

InterPro gives a protein's length and organism under UniProt's keys (`sequence_length`,
`organism`), so that the merge sets both side by side (`147 aa` [uniprot], `147 aa` [interpro]).

**A gene, from HGNC into NCBI Gene, UniProt and Open Targets, and back** - HGNC names it and records its other ids; the `Collector` walks from HGNC into NCBI Gene
and UniProt, from NCBI Gene back into HGNC, and from HGNC into Open Targets by the Ensembl gene:

```php
$collector->collect('hgnc', 'HBB', 1, ['ncbi', 'uniprot']);  // 6.8 s: 11p15.4 [hgnc], 11p15.4 [ncbi]
$collector->collect('ncbi', '3043', 1, ['hgnc']);            // hgnc:4827 among the identifiers
$collector->collect('hgnc', 'HBB', 1, ['opentargets']);      // 2 calls, 0.7 s: 552 associated diseases, 4 drugs
```

**A disease, named in several ontologies** - Open Targets names sickle cell disease `MONDO_0011382` and gives its former ids
(`EFO_0000697`, `Orphanet_232`) and its OMIM and Orphanet ids as identifiers; ClinVar's conditions,
the GWAS Catalog's traits and STRING's enrichment name the same terms (Mondo, EFO, HPO), as related
things.

**A variant, from ClinVar into the GWAS Catalog** - ClinVar gives the sickle cell variant's rs id, which the GWAS Catalog reads:

```php
$collector->collect('clinvar', '15333', 1, ['gwas']);        // 3 calls, 1.7 s
```

`sources` = clinvar, gwas: ClinVar's classification (`Pathogenic`) and the catalogue's
associations (rs334-T, p = 1e-323 for hereditary hemolytic anemias) side by side. An rs id names
a site (rs334 holds four alleles), a ClinVar variation one of them: the two are joined as ClinVar
names them, not proved the same allele.

**A structure from any of its names, into PubChem** - CIR (omnilab/cactus) reads a name, a CAS
number, a SMILES or an InChI and gives the standard InChIKey, which PubChem, ChEBI and ChEMBL read:

```php
$caffeine = $collector->collect('cactus', 'caffeine', 1, ['pubchem']);
$caffeine->sources;                              // ['cactus', 'pubchem']
$caffeine->identifier(Scheme::PUBCHEM_CID);      // 2519
$caffeine->properties('molecular_weight');       // 194.1926 g/mol [cactus], 194.19 g/mol [pubchem]
```

Walked on 2026-10-15 (`.samples/live.php`): CIR's eight calls, then PubChem by the InChIKey.

**The Catalogue of Life, GBIF, iNaturalist and ITIS: the lion** (walked on 2026-10-16) -
`collect('col', '4CGXP', 1, ['gbif', 'inaturalist', 'itis'])`: the Catalogue of Life gives its
GBIF key (the same id: GBIF's taxonomy is the COL eXtended Release since June 2026), iNaturalist
41964 (an id COL names) and ITIS 183803 (the record COL took the lion from); four calls, 5.4 s;
`sources` = col, gbif, inaturalist, itis; `family` Felidae from the four, `taxonomic_status`
accepted [col], accepted [gbif], valid [itis], `iucn_status` Vulnerable (VU) [gbif] and
[inaturalist]. The other way, `collect('gbif', '4CGXP', 1, ['col'])`: omnilab/col reads the GBIF
key as its own id (a legacy number, `gbif:5219404`, is refused: it is the Backbone's); two
sources, 2.4 s.

**The sea: OBIS, WoRMS, ITIS and NCBI, the common dolphin** (walked on 2026-10-16) - OBIS and
WoRMS key a taxon by the same AphiaID (`worms:137094`): the `Merger` takes them for one.
`collect('obis', '137094', 1, ['worms', 'ncbi'])`: OBIS's 121,709 records and NCBI 9728, then
WoRMS's ITIS 180438 and IUCN 134817215 learnt on the way; 7.6 s. `collect('worms', '137094', 1,
['itis', 'ncbi'])`: WoRMS's ITIS and NCBI ids followed; three common names, one a source (common
dolphin [worms], saddle-backed dolphin [itis], saddleback dolphin [ncbi]); 7.4 s (WoRMS's seven
calls weigh most). `collect('col', 'Delphinus delphis', 1, ['worms', 'itis'])`: COL's dolphin
(34JWM) into WoRMS 137094 and ITIS 180438; 8.8 s. WoRMS also reads an ITIS, NCBI or IUCN id, so
an entity carrying one reaches WoRMS. With `omnilab/iucn` and a token, WoRMS's IUCN id leads to
the Red List's assessment (not walked: no token).

**No path: PBDB and eBird** - the Paleobiology Database and eBird give no other base's id;
Wikidata holds theirs (P10907, P3444), which omnilab/wikidata does not read yet. PBDB's fossils
(`fossils()`) are outside the contract.

**No path: the earth, water and sky sources of 2026-10-15/16** - omnilab/emsc, usgs-water, tides,
brightsky, swpc, elevation and openaq give no id of another base (Bright Sky's stations carry
their DWD and WIGOS ids, both its own), and Wikidata has no property for the EMSC's events: the
`Collector` has nothing to follow into them or out of them. The EMSC's and the USGS's records of
one earthquake (the M 5.0 of central Turkey on 2026-10-04 at 11:43:23 UTC: `emsc.event:20261004_0000156`,
mb; `usgs.event:us6000tzip`, mww) stay two entities: joining them by time and place would be a
guess, which the `Merger` does not make.

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
