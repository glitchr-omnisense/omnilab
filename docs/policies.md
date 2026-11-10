---
title: Usage policies
order: 75
---

# Usage policies

Every source says, as data, what its service allows: how many requests (a second, a minute, a
day; per application, key or address; how many in flight), what may be done with the answers
(commercial use, caching, storage, redistribution), the credit to show and the data's licence,
the key or User-Agent it needs, and where all this was read. That is its `policy.json`, in the
schema of [omnisense/contracts](https://github.com/glitchr-omnisense/contracts/blob/1.x/docs/policies.md),
named in its `composer.json` (`extra.omnisense.policy`) and given by the source
(`HttpSource::usagePolicy()`, `DeclaresUsagePolicyInterface`).

Every HTTP request a source makes goes through that policy (`Policy\SourceGuard`, built by the
factory; `HttpSource::guard()` gives the kernel's `PolicyGuard`):

- **a limit reached waits, when its window ends within `max_wait` seconds** (10 by default): a
  per-second limit, one request every three seconds - the sleep the sources' `throttle` did
  before, now read from the policy. Longer (a minute's, an hour's or a day's budget spent), the
  request is **not sent**: a `RateLimitedException` (an `UnavailableException`, as a 429 always
  was) whose `wasSent()` is false, with `$retryAfter` (when the window ends) and `$limit`;
- a service's 429 is a `RateLimitedException` as before (`wasSent()` true, `$retryAfter` read
  from Retry-After in seconds or as a date), and it holds the next requests for its Retry-After;
  a 503's Retry-After and a `RateLimit` header field with nothing left hold them too - waited
  out within `max_wait`, refused beyond;
- every request sent counts, failed or not (the service counted it too);
- a source that declares nothing has an unknown policy: nothing limited, a 429 still held.

Why wait rather than refuse: callers relied on the sources sleeping between calls (the Collector
walks several sources in one call; arXiv's one request every three seconds), so the short waits
stay waits; a long one would block a page or a worker for minutes, so it refuses, as the service
itself would have answered 429. `max_wait: 0` refuses at once.

```php
$registry = new Registry($factories, [
    'pubchem' => ['factory' => 'pubchem', 'options' => ['mailto' => 'support@glitchr.io', 'max_wait' => 5]],
    'meteo' => ['factory' => 'open-meteo', 'usage' => ['commercial' => false]],
    'gbif' => ['factory' => 'gbif', 'policy' => ['rateLimits' => [['max' => 2, 'window' => 'second']]]],
], $limiter);   // where requests are counted: in memory by default; shared across processes
                // with omnibridge/symfony's RateLimiter over symfony/rate-limiter

$registry->policy('pubchem')->rights->attribution->text;   // the credit to show
$registry->guard('pubchem')->remaining(HttpSource::REQUEST);
foreach ($registry->policies() as $name => $policy) { /* a "sources and their terms" page */ }
```

- `policy` (beside `options` or in them) tightens the declared policy (`UsagePolicy::tighten()`):
  the site's limits are added (each counts, the strictest wins), one counting the same requests
  with a higher maximum is refused, permissions only go stricter (`allowed` < `unknown` <
  `contract` < `forbidden`), nothing required is lifted, the credit and licence cannot be
  rewritten. Anything looser is an `InvalidPolicyException` at the source's first request.
- `usage` says what the site is (`commercial`, `open_source`, `contract`): a use the terms forbid
  fails at the first request (`UsageNotPermitted`), before anything is sent.
- `throttle` stays: seconds left between two requests, the site's own spacing on top of the
  policy. It can slow a source down, never speed it past its policy.
- An operation is an HTTP request (`HttpSource::REQUEST`, "request"): the services count
  requests. A source calling APIs with limits of their own names them (`operation()`): NCBI's `eutils` and `datasets`, NOAA's `ncei` and `nws`. Some figures depend on the options: NCBI's and ClinVar's with a key (`usagePolicy()` in those sources).

## The sources' policies

Read at each service's own pages on the date given (`provenance` in each file: the terms, the
rate-limit page, notes on what was read and what was not). Nothing is declared that the service
does not publish: an unknown limit is `null`, and a figure seen only elsewhere is in the notes.

| Source | Limits | Licence; commercial use | Credit | Needs | Read |
|---|---|---|---|---|---|
| brightsky | unknown | CC-BY-4.0; commercial allowed | required: Source: Deutscher Wetterdienst | - | 2026-11-10 |
| cactus | unknown | not stated; commercial unknown | asked: NCI/CADD Group Chemical Identifier Resolver; OPSIN: Lowe D.M. et al. (2011) J. Chem. Inf. Model. 51, 739-753 | - | 2026-11-10 |
| cams | unknown | Licence to use Copernicus Products (rev. 12); commercial allowed | required: Generated using Copernicus Atmosphere Monitoring Service information [Year] | - | 2026-11-10 |
| cds | unknown | not stated; commercial unknown | required: Generated using Copernicus Climate Change Service information [Year] | - | 2026-11-10 |
| chebi | unknown | CC-BY-4.0; commercial allowed | required: ChEBI (EMBL-EBI). Malik A. et al. (2025) ChEBI: re-engineered for a sustainable future. Nucleic Acids Res. doi:10.1093/nar/gkaf1271 | - | 2026-11-10 |
| chembl | unknown | CC-BY-SA-3.0; commercial allowed | required: ChEMBL (EMBL-EBI). Zdrazil B. et al. (2023) The ChEMBL Database in 2023. Nucleic Acids Res. doi:10.1093/nar/gkad1004; web services: Davies M. et al. (2015) Nucleic Acids Res. 43(W1):W612-20, doi:10.1093/nar/gkv352 | - | 2026-11-10 |
| clinvar | 3 a second per client-ip | not stated; commercial allowed | required: ClinVar (NCBI), cite a ClinVar publication | User-Agent | 2026-11-10 |
| cod | unknown | CC0-1.0; commercial allowed | asked: Crystallography Open Database (COD), with the original authors of the structural data | - | 2026-11-10 |
| codata | unknown | not stated; commercial unknown | asked: NIST Standard Reference Database 121, CODATA Internationally Recommended Values of the Fundamental Physical Constants | - | 2026-11-10 |
| col | unknown | CC-BY-4.0; commercial allowed | required: Bánki, O., Roskov, Y., Döring, M., et al. (2026). Catalogue of Life (2026-10-16 XR). Catalogue of Life Foundation, Amsterdam, Netherlands. https://doi.org/10.48580/dgzlf | - | 2026-11-10 |
| copernicus | unknown; 4 in flight | Copernicus Sentinel Data legal notice (Regulation (EU) No 1159/2013); commercial allowed | required: Copernicus Sentinel data [Year] | - | 2026-11-10 |
| donki | unknown | not stated; commercial unknown | asked: We acknowledge the Community Coordinated Modeling Center (CCMC) at Goddard Space Flight Center for the use of DONKI, https://ccmc.gsfc.nasa.gov/tools/DONKI/ | - | 2026-11-10 |
| earthdata | unknown | CC0-1.0; commercial allowed | asked: NASA Earthdata (cite each collection by its DOI) | - | 2026-11-10 |
| ebird | unknown | not stated; commercial contract | required: eBird.org | - | not read (see notes) |
| elevation | 1 a second, 1,000 a day | not stated; commercial unknown | - | - | 2026-11-10 |
| emsc | unknown | CC-BY-4.0; commercial allowed | required: Credit: EMSC-CSEM SeismicPortal, https://www.seismicportal.eu | - | 2026-11-10 |
| ensembl | 55,000 a hour per client-ip, 15 a second per client-ip | not stated; commercial allowed | required: Ensembl (EMBL-EBI); cite the current Ensembl paper | - | 2026-11-10 |
| exoplanets | unknown | not stated; commercial unknown | asked: This research has made use of the NASA Exoplanet Archive, which is operated by the California Institute of Technology, under contract with the National Aeronautics and Space Administration under the Exoplanet Exploration Program. | - | not read (see notes) |
| gaia | unknown | CC BY-NC 3.0 IGO; commercial contract | required: Credit: ESA, Gaia DPAC | - | 2026-11-10 |
| gbif | unknown | Per dataset: CC0-1.0, CC-BY-4.0 or CC-BY-NC-4.0; commercial unknown | required: GBIF.org, with the DOI of each dataset or download used | - | 2026-11-10 |
| gwas | 15 a second per client-ip, 100 a minute per client-ip | EMBL-EBI Terms of Use (summary statistics CC0-1.0 unless otherwise stated); commercial allowed | required: NHGRI-EBI GWAS Catalog | - | 2026-11-10 |
| hgnc | 10 a second | CC0-1.0; commercial allowed | asked: Genenames.org: the HGNC and PGNC resources in 2026, Nucleic Acids Res. (HGNC, RRID:SCR_002827) | - | 2026-11-10 |
| horizons | unknown; 1 in flight | not stated; commercial unknown | - | User-Agent | 2026-11-10 |
| inaturalist | 60 a minute, 10,000 a day | Per observation and per photo: CC0-1.0, CC-BY-4.0, CC-BY-NC-4.0 (the default), other CC variants, or all rights reserved; commercial unknown | required: iNaturalist, with each observer and the licence of each observation and photo | - | 2026-11-10 |
| interpro | unknown | CC0-1.0; commercial allowed | asked: Blum M. et al., "InterPro: the protein sequence classification resource in 2025", Nucleic Acids Res. 2025, doi:10.1093/nar/gkae1082 | - | 2026-11-10 |
| itis | unknown | CC0-1.0; commercial allowed | asked: Retrieved [month, day, year], from the Integrated Taxonomic Information System (ITIS) on-line database, www.itis.gov, CC0 https://doi.org/10.5066/F7KH0KBK | - | 2026-11-10 |
| iucn | unknown | not stated; commercial contract | required: IUCN 2026. IUCN Red List of Threatened Species. Version 2026-1 <www.iucnredlist.org> | key | not read (see notes) |
| jpl | unknown; 1 in flight | not stated; commercial unknown | - | User-Agent | 2026-11-10 |
| kegg | 3 a second | not stated; commercial contract | asked: KEGG, Kanehisa Laboratories | - | 2026-11-10 |
| launches | 15 a hour | not stated; commercial unknown | - | - | 2026-11-10 |
| materials-project | 25 a second per key | CC-BY-4.0; commercial unknown | required: Jain A. et al., The Materials Project: A materials genome approach to accelerating materials innovation, APL Materials 1, 011002 (2013) | key | 2026-11-10 |
| mpc | 5 a second | not stated; commercial unknown | - | - | 2026-11-10 |
| ncbi | 3 a second (eutils) per client-ip, 5 a second (datasets) | not stated; commercial allowed | required: NCBI Disclaimer and Copyright notice | User-Agent | 2026-11-10 |
| ned | unknown | not stated; commercial unknown | asked: This research has made use of the NASA/IPAC Extragalactic Database, which is funded by the National Aeronautics and Space Administration and operated by the California Institute of Technology. | - | 2026-11-10 |
| noaa | unknown | US public domain (17 U.S.C. 105); commercial allowed | asked: NOAA National Centers for Environmental Information (NCEI); NOAA National Weather Service | User-Agent | 2026-11-10 |
| obis | unknown; 1 in flight | Per dataset: CC0-1.0, CC-BY-4.0 or CC-BY-NC-4.0; commercial unknown | required: OBIS (2026) Ocean Biodiversity Information System. Intergovernmental Oceanographic Commission of UNESCO. obis.org. | - | 2026-11-10 |
| open-meteo | 600 a minute, 5,000 a hour, 10,000 a day, 300,000 a month | CC-BY-4.0; commercial contract | required: Weather data by Open-Meteo.com | - | 2026-11-10 |
| openaq | 60 a minute per key, 2,000 a hour per key | not stated; commercial unknown | required: OpenAQ | key | 2026-11-10 |
| opentargets | unknown | CC0-1.0; commercial allowed | asked: Buniello, A. et al. (2025). Open Targets Platform: facilitating therapeutic hypotheses building in drug discovery. Nucleic Acids Research. | - | 2026-11-10 |
| oqmd | unknown | CC-BY-4.0; commercial allowed | required: Saal J.E. et al., JOM 65, 1501-1509 (2013); Kirklin S. et al., npj Comput. Mater. 1, 15010 (2015) | - | 2026-11-10 |
| pbdb | unknown | CC-BY-4.0; commercial allowed | required: The data were downloaded from the Paleobiology Database on [date], using the following parameters: [...] | - | 2026-11-10 |
| pdb | unknown | CC0-1.0; commercial allowed | asked: RCSB PDB and the original authors of the structure | - | 2026-11-10 |
| pubchem | 5 a second, 400 a minute | not stated; commercial unknown | asked: PubChem, National Center for Biotechnology Information (NLM/NIH) | - | not read (see notes) |
| quickgo | unknown | CC-BY-4.0; commercial allowed | required: Gene Ontology data (release date / DOI), CC BY 4.0; cite Ashburner et al. 2000 and The Gene Ontology Consortium, "The Gene Ontology knowledgebase in 2026", Nucleic Acids Res., doi:10.1093/nar/gkaf1292 | - | 2026-11-10 |
| reactome | unknown | CC0-1.0; commercial allowed | asked: The Reactome Knowledgebase 2026, Nucleic Acids Res. 2025, doi:10.1093/nar/gkaf1223 | - | 2026-11-10 |
| rhea | unknown | CC-BY-4.0; commercial allowed | required: Rhea (SIB Swiss Institute of Bioinformatics) | - | 2026-11-10 |
| simbad | 10 a second per client-ip, 400 every 10 seconds per client-ip | ODbL-1.0; commercial allowed | required: This research has made use of the SIMBAD database, CDS, Strasbourg Astronomical Observatory, France | - | not read (see notes) |
| string | 1 a second; 1 in flight | CC-BY-4.0; commercial allowed | required: STRING (version), with any changes or additions indicated; cite Szklarczyk D. et al., Nucleic Acids Res. 2025, doi:10.1093/nar/gkae1113 | User-Agent | 2026-11-10 |
| swpc | unknown | US public domain (17 U.S.C. 105); commercial allowed | asked: NOAA Space Weather Prediction Center | - | 2026-11-10 |
| tides | unknown | US public domain (17 U.S.C. 105); commercial allowed | asked: NOAA/NOS/CO-OPS (Tides and Currents) | - | 2026-11-10 |
| uniprot | unknown | CC-BY-4.0; commercial allowed | required: UniProt Consortium, UniProt (CC BY 4.0) | - | 2026-11-10 |
| usgs | unknown | US public domain (17 U.S.C. 105); commercial allowed | asked: Credit: U.S. Geological Survey | - | 2026-11-10 |
| usgs-water | unknown | US public domain (17 U.S.C. 105); commercial allowed | asked: U.S. Geological Survey, Water Data for the Nation | - | 2026-11-10 |
| vizier | unknown | not stated; commercial unknown | asked: This research has made use of the VizieR catalogue access tool, CDS, Strasbourg Astronomical Observatory, France (DOI: 10.26093/cds/vizier) | - | not read (see notes) |
| wikidata | 200 a minute; 3 in flight | CC0-1.0; commercial allowed | asked: Wikidata | User-Agent | 2026-11-10 |
| worms | unknown | CC-BY-4.0; commercial allowed | required: WoRMS Editorial Board (2026). World Register of Marine Species. Available from https://www.marinespecies.org at VLIZ. Accessed [date]. doi:10.14284/170 | - | 2026-11-10 |
