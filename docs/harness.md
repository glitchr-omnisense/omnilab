# The Docker harness

`docker/` runs this package with every `omnilab/*` source installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNILAB_PLUGINS=../..` is set in `docker/.env` - and
a console that asks the real services.

```sh
cd docker && cp .env.dist .env       # OMNILAB_MAILTO, WIKIDATA_LANGUAGE, INATURALIST_LOCALE, an NCBI or Open-Meteo key if you have one
docker compose run --rm omnilab sources
```

Open-Meteo is configured twice: `open-meteo` (the weather) and `air-quality` (`api:
air_quality`).

| Command | |
|---|---|
| `sources` | the sources installed and configured, and what each can do |
| `entity <source> <id>` | one entity, by an identifier or a name (JSON) |
| `search <source> <text>` | `--domain`, `--limit` |
| `collect <source> <id>` | one entity, its cross-references followed into the other sources, merged (JSON); `--depth` |
| `series <source> <place> <variable>...` (a place `lat,lon`, a box, or a station: `ghcn:USW00094728`, `nws.station:KNYC`) | `--from`, `--to`, `--daily mean\|max\|min\|sum`, `--step PT15M`, `--timezone` |
| `products <source>` | `--point` or `--bbox`, `--collection` (repeatable), `--from`, `--to`, `--cloud`, `--oldest`, `--max`, `--json` |
| `occurrences <source>` | `--point` and `--radius` (km), or `--bbox`; `--from`, `--to`; `--filter key=value` (repeatable, a key twice makes a list: `-f taxon=4CGXP -f country=ZA`, `-f min_magnitude=4`); `--sort`; `--max`, `--json` |
| `bare` | plain PHP: the registry built by hand, each shape asked of its service, what PHP loaded |
| `test` | every package's tests |

`--from` and `--to` take whatever PHP's `DateTimeImmutable` reads: `2026-09-01`, `tomorrow`,
`--from=-10days` (with the `=`, or the console takes the minus for an option).

```sh
docker compose run --rm omnilab entity pubchem caffeine
docker compose run --rm omnilab entity pubchem CHEBI:27732
docker compose run --rm omnilab search pubchem caffeine --limit 5
docker compose run --rm omnilab collect pubchem caffeine            # then ChEBI and Wikidata, merged
docker compose run --rm omnilab entity chebi CHEBI:27732
docker compose run --rm omnilab entity wikidata Q60235
docker compose run --rm omnilab search wikidata caffeine --domain chemistry --limit 5
docker compose run --rm omnilab entity uniprot P68871
docker compose run --rm omnilab collect uniprot P68871              # then NCBI Gene and Wikidata, merged
docker compose run --rm omnilab entity ncbi txid9689
docker compose run --rm omnilab search ncbi "Panthera[subtree]" --domain taxonomy
docker compose run --rm omnilab entity gbif 4CGXP                  # or 5219404, "Panthera leo"
docker compose run --rm omnilab collect gbif "Panthera leo"         # then NCBI Taxonomy and Wikidata, merged
docker compose run --rm omnilab entity ensembl HBB                 # or ENSG00000244734, mus_musculus/Hbb-bs
docker compose run --rm omnilab collect ensembl ENSG00000244734     # then NCBI Gene, UniProt and Wikidata (P594), merged
docker compose run --rm omnilab entity pdb 4HHB                     # its chains' proteins and its ligands related
docker compose run --rm omnilab search pdb hemoglobin --limit 5
docker compose run --rm omnilab entity inaturalist 41964            # or "Panthera leo"
docker compose run --rm omnilab entity chembl CHEMBL113
docker compose run --rm omnilab entity simbad Betelgeuse
docker compose run --rm omnilab entity jpl 433
docker compose run --rm omnilab entity usgs us7000pn9s
docker compose run --rm omnilab collect wikidata Q133574091         # the 2025 Myanmar earthquake, then the USGS by P3196
docker compose run --rm omnilab series usgs 35.6812,139.7671 count magnitude --daily max --from 2026-10-01 --to 2026-10-07
docker compose run --rm omnilab series noaa ghcn:USW00094728 TMAX PRCP --step P1D --from 2026-10-01 --to 2026-10-07   # a station's own records
docker compose run --rm omnilab entity noaa ghcn:FR000007190                                                    # the station itself
docker compose run --rm omnilab series open-meteo 48.5734,7.7521 temperature_2m --from tomorrow --to tomorrow
docker compose run --rm omnilab series open-meteo 48.5734,7.7521 temperature_2m precipitation_sum --daily max --from 2026-09-01 --to 2026-09-07
docker compose run --rm omnilab series air-quality 48.5734,7.7521 pm2_5 nitrogen_dioxide european_aqi --from 2026-10-14 --to 2026-10-14
docker compose run --rm omnilab series air-quality 48.5734,7.7521 european_aqi --daily max --from 2026-10-08 --to 2026-10-14
docker compose run --rm omnilab occurrences gbif --point -24.99,31.59 --radius 100 --from 2025-01-01 --to 2025-12-31 -f taxon=4CGXP --max 20
docker compose run --rm omnilab occurrences inaturalist --point -24.99,31.59 --radius 100 --from 2025-01-01 --to 2025-12-31 -f taxon=41964 --max 20
docker compose run --rm omnilab occurrences usgs --bbox -25,35,45,72 --from 2026-10-01 --to 2026-10-07 -f min_magnitude=4 --sort magnitude
docker compose run --rm omnilab products copernicus --point 48.5734,7.7521 --from=-10days --cloud 30
docker compose run --rm omnilab products copernicus --bbox 7.68,48.53,7.83,48.64 --collection sentinel-1-grd --from=-3days
```

## Bare: no bundle, no container

The console above is a `symfony/console` application over a registry built by hand; `bare` is
less still - one PHP script, `docker/harness/bin/bare`, that requires the autoloader and nothing
else. It builds the `Registry` from the source packages installed, asks each what it can do, then
asks each shape of its real service - caffeine of PubChem, followed by the `Collector` into ChEBI
and Wikidata, tomorrow's hourly temperature in Strasbourg of Open-Meteo, the Sentinel-2 L2A scenes
over Strasbourg in the last ten days under 30 % cloud of the Copernicus Data Space, the largest
earthquakes (five at most) of magnitude 4 or more over Europe in the last seven days of the USGS - or,
with `--recorded`, reads the answers kept in
`docker/harness/recorded/`; then it lists what PHP loaded and exits 1 if a class of a framework is
among it (`Symfony\Component\DependencyInjection`, `Config`, `HttpKernel`, `HttpFoundation`, a
bundle, Doctrine, Twig):

```
$ docker compose run --rm omnilab bare
Omnilab in bare PHP: the registry built by hand, no bundle, no container.

  pubchem      entity search structure cross_references
  open-meteo   series forecast historical
  air-quality  series forecast historical
  copernicus   products area cloud_cover
  chebi        entity search structure cross_references
  wikidata     entity search cross_references
  chembl       entity search structure cross_references
  uniprot      entity search cross_references
  ncbi         entity search cross_references
  gbif         entity search occurrences area cross_references
  simbad       entity search
  jpl          entity search
  usgs         entity series occurrences historical area
  ensembl      entity search cross_references
  pdb          entity search structure
  noaa         series historical
  nws          series forecast historical
  cds          products area
  cams         products area
  earthdata    products area cloud_cover
  inaturalist  entity search occurrences area

PubChem, caffeine, asked of pubchem.ncbi.nlm.nih.gov:
  pubchem.cid:2519  Caffeine
  molecular_formula  C8H10N4O2
  molecular_weight   194.19 g/mol
  xlogp              -0.1
  tpsa               58.4 Å²
  inchikey           RYYVLZVUVIJVGH-UHFFFAOYSA-N
  identifiers        inchikey:RYYVLZVUVIJVGH-UHFFFAOYSA-N inchi:InChI=1S/C8H10N4O2/c1-10-4-9-6-5(10)7(13)12(3)8(14)11(6)2/h4H,1-3H3 cas:58-08-2 dsstox:DTXSID0020232 chebi:27732 chembl:CHEMBL113 unii:3G6A5W338E kegg:C07481 wikidata:Q60235

The Collector, caffeine from PubChem followed into ChEBI and Wikidata, asked of each:
  sources            pubchem, chebi, wikidata
  molecular_weight   194.19 g/mol [pubchem]  194.194 g/mol [chebi]
  monoisotopic_mass  194.08037557 Da [pubchem]  194.08038 Da [chebi]
  is_a               purine alkaloid [chebi]  trimethylxanthine [chebi]
  melting_point      234 °C [wikidata]  235 °C [wikidata]
  density            1.23 g/cm³ [wikidata]

Open-Meteo, the hourly temperature in Strasbourg on 2026-10-16, asked of api.open-meteo.com:
  00:00 12.2  01:00 11  02:00 10.8  03:00 10.1  04:00 10  05:00 10.1  06:00 10  07:00 10.1  08:00 10.2  09:00 10.3  10:00 10.7  11:00 11.4  12:00 13  13:00 15.8  14:00 18.1  15:00 19.8  16:00 20.9  17:00 21.3  18:00 21.1  19:00 20  20:00 19  21:00 18.2  22:00 17.5  23:00 17.2 (°C)

Copernicus, the Sentinel-2 L2A scenes over Strasbourg in the last ten days under 30 % cloud, asked of stac.dataspace.copernicus.eu:
  2026-10-12 10:29 UTC  28.94 %  MGRS-32UMU  S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607
  2026-10-12 10:29 UTC   1.84 %  MGRS-32ULU  S2A_MSIL2A_20261012T102911_N0513_R108_T32ULU_20261012T170607
  2026-10-05 10:28 UTC  11.10 %  MGRS-32UMU  S2B_MSIL2A_20261005T102859_N0513_R108_T32UMU_20261005T142235
  2026-10-05 10:28 UTC   5.62 %  MGRS-32ULU  S2B_MSIL2A_20261005T102859_N0513_R108_T32ULU_20261005T142235

USGS, the largest earthquakes (five at most) of magnitude 4 or more over Europe in the last seven days, asked of earthquake.usgs.gov:
  2026-10-09 04:46:52 UTC  35.1045,23.4684      M 4.6 - 23 km SW of Palaióchora, Greece
  2026-10-10 22:33:28 UTC  35.4717,26.5886      M 4.2 - 31 km WNW of Fry, Greece

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle, Doctrine, Twig): none
```

(as answered on 2026-10-15, in the afternoon: Open-Meteo's forecast for the 16th had moved since
the morning's 10.4 to 20.4 °C)

`bare --recorded --json` prints the same whole, every class and file loaded, without a call:
`Tests/BareTest.php` runs it in a process of its own and checks the list.

The image is `php:8.4-cli-alpine` with Composer; the harness's packages live in the `harness`
volume of the `omnilab-harness` project.
