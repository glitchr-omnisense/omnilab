# Datasets, requests, files

Three things the catalogues of data (the Copernicus data stores, NASA's CMR) do beside listing
products, which two sources did by methods of their own and the contract now names:

| | Asks | Answers | Capability |
|---|---|---|---|
| `datasets(Query)` | a text | a `Page` of `Product`, each a **dataset** (a catalogue's entry: ERA5-Land, HLSS30 v2.0), the best match first; its `id` is what `ProductQuery::$collections` takes | `Capability::DATASETS` |
| `submit($dataset, $request)`, `job($id)`, `jobs(ProductQuery)` (`JobSourceInterface`) | a request for a file of a dataset (its inputs, the dataset's own names), then the request by its id | a `Job`: its status and progress, once successful its file | `Capability::JOBS` |
| `open(Asset\|string)` | an asset (a granule's band, a job's file) or its address | the HTTP response, streamed, with the source's credentials sent | `Capability::OPEN` |

Nothing is decoded: a NetCDF, a GRIB, a GeoTIFF, a zip is the application's to read.

## Datasets

```php
use Omnilab\Cds\CdsSourceFactory;
use Omnilab\Earthdata\EarthdataSourceFactory;
use Omnilab\Source\Query;
use Symfony\Component\HttpClient\HttpClient;

$http = HttpClient::create();
foreach ([(new CdsSourceFactory($http))->create([]), (new EarthdataSourceFactory($http))->create([])] as $source) {
    $page = $source->datasets(new Query('sea surface temperature', limit: 3));
    printf("%s, %d datasets:\n", $source->getName(), $page->total);
    foreach ($page as $dataset) {
        printf("  %s  %s  %s..%s\n", $dataset->id, $dataset->properties['title'] ?? '', $dataset->start?->format('Y-m-d'), $dataset->end?->format('Y-m-d') ?? '');
    }
}
```

```
cds, 68 datasets:
  satellite-sea-surface-temperature  Global sea surface temperature derived from satellite observations  1979-07-13..2026-09-23
  satellite-ist-sst-global  Combined sea surface and sea ice surface temperature data derived from satellite observations  1982-01-01..2026-09-12
  satellite-sea-surface-temperature-ensemble-product  Sea surface temperature daily gridded data from 1981 to 2016 derived from a multi-product satellite-based ensemble  1981-09-01..2016-12-31
earthdata, 4111 datasets:
  OSTIA-UKMO-L4-GLOB-REP-v2.0_2.0  GHRSST Level 4 OSTIA Global Historical Reprocessed Foundation Sea Surface Temperature Analysis produced by the UK Meteorological Office  1982-01-01..2024-01-01
  EO:EUM:DAT:SENTINEL-3:SL_2_WST___NRT_2017-07-05  SLSTR Sea Surface Temperatures (SST) in NRT - Sentinel-3  2017-07-05..
  EO:EUM:DAT:SENTINEL-3:SL_2_WST___NTC_2017-07-05  SLSTR Sea Surface Temperatures (SST) in NTC - Sentinel-3  2017-07-05..
```

(as answered on 2026-10-15; the dataset's id then goes into `new ProductQuery([$dataset->id], ...)`)

| | cds, cams | earthdata | copernicus |
|---|---|---|---|
| `datasets()` | the store's catalogue search, by relevance, paged by number | the CMR's collection search (`keyword`), paged by `CMR-Search-After` | no: its catalogue's free-text search ranks nothing (alphabetical), breaks on a space (`land surface temperature`: a database's syntax error), and matches no dataset for "temperature"; its 427 collections whole weigh 3.5 MB and took over a minute (2026-10-15) |

## Asynchronous requests: `Job`

Some data are made on request: the Climate and Atmosphere Data Stores cut ERA5 or a CAMS forecast
to the variables, days and area asked, queue the request, then keep the file for a while. A
source that takes such requests implements `Source\JobSourceInterface`:

```php
$job = $cds->submit('reanalysis-era5-single-levels', ['product_type' => ['reanalysis'], 'variable' => ['2m_temperature'], 'year' => ['2025'], 'month' => ['07'], 'day' => ['01'], 'time' => ['12:00'], 'data_format' => 'netcdf', 'area' => [49, 7, 48, 8]]);
while (!$job->isDone()) {
    sleep(10);                       // the source never waits: the application asks again
    $job = $cds->job($job->id);
}
if ($job->isSuccessful()) {
    $response = $cds->open($job->file()); // streamed: $response->getContent(), or HttpClient's stream()
}
```

(not run against the store: a request needs an account's key, and the dataset's licence accepted;
without one the store answers `401 Authentication failed: operation not allowed`, as
[sources](sources.md#errors) shows)

A `Job` is a `Product` in the making - the dataset as its `collection`, created, started and
finished as its `datetime`, `start` and `end`, the file once made as its `data` asset - with:

| | |
|---|---|
| `status` | `Job::ACCEPTED` (queued), `RUNNING`, `SUCCESSFUL`, `FAILED`, `REJECTED`, `DISMISSED` (OGC API - Processes' words, the stores'), as the service says |
| `progress` | percent, when the service says (100 once successful); null otherwise |
| `message` | why it failed, when the service says |
| `isDone()`, `isSuccessful()`, `file()` | ended (successful, failed, rejected, dismissed); successful; the file's `Asset` (null before) |

`jobs(ProductQuery)` lists the account's requests, a page at a time, newest first (oldest with
`ProductQuery::OLDEST`); a store that lists them all together refuses a query that names datasets,
a place or a period.

Why a `Job` extends `Product`, and why a separate interface: [decisions](decisions.md).
