# The Docker harness

`docker/` runs this package with every `omnilab/*` source installed - from GitHub (branch 1.x),
or from the checkouts beside this one when `OMNILAB_PLUGINS=../..` is set in `docker/.env` - and
a console that asks the real services.

```sh
cd docker && cp .env.dist .env       # OMNILAB_MAILTO, WIKIDATA_LANGUAGE, an NCBI or Open-Meteo key if you have one
docker compose run --rm omnilab sources
```

| Command | |
|---|---|
| `sources` | the sources installed and configured, and what each can do |
| `entity <source> <id>` | one entity, by an identifier or a name (JSON) |
| `search <source> <text>` | `--domain`, `--limit` |
| `collect <source> <id>` | one entity, its cross-references followed into the other sources, merged (JSON); `--depth` |
| `series <source> <place> <variable>...` | `--from`, `--to`, `--daily mean\|max\|min\|sum`, `--step PT15M`, `--timezone` |
| `products <source>` | `--point` or `--bbox`, `--collection` (repeatable), `--from`, `--to`, `--cloud`, `--oldest`, `--max`, `--json` |
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
docker compose run --rm omnilab series open-meteo 48.5734,7.7521 temperature_2m --from tomorrow --to tomorrow
docker compose run --rm omnilab series open-meteo 48.5734,7.7521 temperature_2m precipitation_sum --daily max --from 2026-09-01 --to 2026-09-07
docker compose run --rm omnilab products copernicus --point 48.5734,7.7521 --from=-10days --cloud 30
docker compose run --rm omnilab products copernicus --bbox 7.68,48.53,7.83,48.64 --collection sentinel-1-grd --from=-3days
```

## Bare: no bundle, no container

The console above is a `symfony/console` application over a registry built by hand; `bare` is
less still - one PHP script, `docker/harness/bin/bare`, that requires the autoloader and nothing
else. It builds the `Registry` from the source packages installed, asks each what it can do, then
asks each shape of its real service - caffeine of PubChem, followed by the `Collector` into ChEBI
and Wikidata, tomorrow's hourly temperature in Strasbourg of Open-Meteo, the Sentinel-2 L2A scenes
over Strasbourg in the last ten days under 30 % cloud of the Copernicus Data Space - or, with `--recorded`, reads the answers kept in
`docker/harness/recorded/`; then it lists what PHP loaded and exits 1 if a class of a framework is
among it (`Symfony\Component\DependencyInjection`, `Config`, `HttpKernel`, `HttpFoundation`, a
bundle, Doctrine, Twig):

```
$ docker compose run --rm omnilab bare
Omnilab in bare PHP: the registry built by hand, no bundle, no container.

  pubchem      entity search structure cross_references
  open-meteo   series forecast historical
  copernicus   products area cloud_cover
  chebi        entity search structure cross_references
  wikidata     entity search cross_references

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
  00:00 12.2  01:00 11.3  02:00 11  03:00 10.7  04:00 10.7  05:00 10.6  06:00 10.5  07:00 10.4  08:00 10.4  09:00 10.8  10:00 11.4  11:00 12  12:00 13.2  13:00 15.4  14:00 17.3  15:00 18.7  16:00 19.9  17:00 20.4  18:00 20.2  19:00 19.3  20:00 18.5  21:00 18  22:00 17.3  23:00 16.6 (°C)

Copernicus, the Sentinel-2 L2A scenes over Strasbourg in the last ten days under 30 % cloud, asked of stac.dataspace.copernicus.eu:
  2026-10-12 10:29 UTC  28.94 %  MGRS-32UMU  S2A_MSIL2A_20261012T102911_N0513_R108_T32UMU_20261012T170607
  2026-10-12 10:29 UTC   1.84 %  MGRS-32ULU  S2A_MSIL2A_20261012T102911_N0513_R108_T32ULU_20261012T170607
  2026-10-05 10:28 UTC  11.10 %  MGRS-32UMU  S2B_MSIL2A_20261005T102859_N0513_R108_T32UMU_20261005T142235
  2026-10-05 10:28 UTC   5.62 %  MGRS-32ULU  S2B_MSIL2A_20261005T102859_N0513_R108_T32ULU_20261005T142235

Loaded from Symfony: Symfony\Component\HttpClient, Symfony\Contracts\HttpClient, Symfony\Contracts\Service
Classes of a framework (DependencyInjection, Config, HttpKernel, HttpFoundation, a bundle, Doctrine, Twig): none
```

(as answered on 2026-10-15)

`bare --recorded --json` prints the same whole, every class and file loaded, without a call:
`Tests/BareTest.php` runs it in a process of its own and checks the list.

The image is `php:8.4-cli-alpine` with Composer; the harness's packages live in the `harness`
volume of the `omnilab-harness` project.
