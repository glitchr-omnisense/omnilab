<?php

namespace Omnilab\Tests;

use Omnilab\Exception\ProviderException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Source\HttpSource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * What a ProviderException says of an error answer, in each form the
 * services write it: the bodies below were answered on 2026-10-15 to a
 * wrong call (a trace id or a traceback shortened).
 */
final class HttpSourceErrorsTest extends TestCase
{
    /** @return iterable<string, array{int, string, string}> */
    public static function answers(): iterable
    {
        yield 'Open-Meteo' => [400, '{"error":true,"reason":"Invalid value: Cannot initialize from invalid String value nope"}', 'Invalid value: Cannot initialize from invalid String value nope'];
        yield 'PubChem' => [400, '{"Fault": {"Code": "PUGREST.BadRequest", "Message": "Missing CID list", "Details": ["Expected a comma-separated list of CIDs via URL or POST"]}}', 'Missing CID list (Expected a comma-separated list of CIDs via URL or POST)'];
        yield 'CDS' => [401, '{"type":"permission denied","title":"Authentication failed","status":401,"detail":"operation not allowed","instance":"https://cds.climate.copernicus.eu/api/retrieve/v1/processes/reanalysis-era5-single-levels/execution","trace_id":"41986fb1"}', 'Authentication failed: operation not allowed'];
        yield 'NWS' => [400, '{"correlationId": "9061c6d", "parameterErrors": [{"parameter": "query.start", "message": "Invalid date-time \"bad\", expected format YYYY-MM-DDThh:mm:ssZ or YYYY-MM-DDThh:mm:ss+hh:mm"}], "title": "Bad Request", "type": "https://api.weather.gov/problems/BadRequest", "status": 400, "detail": "Bad Request"}', 'Bad Request (query.start: Invalid date-time "bad", expected format YYYY-MM-DDThh:mm:ssZ or YYYY-MM-DDThh:mm:ss+hh:mm)'];
        yield 'Copernicus STAC, a detail' => [400, '{"detail":{"code":"CollectionInQuerryDoesNotExist","message":"One or more requested collections do not exist.","collections":["nope"]}}', 'One or more requested collections do not exist.'];
        yield 'Copernicus STAC, a plain detail' => [400, '{"detail":"Invalid RFC3339 datetime."}', 'Invalid RFC3339 datetime.'];
        yield 'Copernicus STAC, a description' => [400, '{"code":"AssertionError","description":"BBox \'1,2,3\' must have 4 or 6 values."}', 'BBox \'1,2,3\' must have 4 or 6 values.'];
        yield 'NCEI' => [400, '{"errorCode":400,"errorMessage":"Bad Request","errors":[{"field":"startDate","message":"The start date can not be after the end date.","value":"2026-01-05"},{"field":"endDate","message":"The end date can not be before the start date.","value":"2026-01-02"}]}', 'Bad Request (startDate: The start date can not be after the end date.; endDate: The end date can not be before the start date.)'];
        yield 'NCEI, no field' => [400, '{"errorCode":400,"errorMessage":"Bad Request","errors":[{"field":null,"message":": Invalid search options, dataset: Unknown dataset","value":null}]}', 'Bad Request (: Invalid search options, dataset: Unknown dataset)'];
        yield 'CMR' => [400, '{"errors":["start_date [2026-10-07T00:00:00Z] must be before end_date [2026-10-01T00:00:00Z]"]}', 'start_date [2026-10-07T00:00:00Z] must be before end_date [2026-10-01T00:00:00Z]'];
        yield 'iNaturalist v2' => [422, '{"status":"422","errors":[{"errorCode":"422","message":"Result window is too large, page x size must be less than or equal to [10000]."}]}', 'Result window is too large, page x size must be less than or equal to [10000].'];
        yield 'iNaturalist v1' => [403, '{"error":"Result window is too large, page x size must be less than or equal to [10000].","status":403}', 'Result window is too large, page x size must be less than or equal to [10000].'];
        yield 'UniProt' => [400, '{"url":"http://rest.uniprot.org/uniprotkb/search","messages":["query parameter has an invalid syntax"]}', 'query parameter has an invalid syntax'];
        yield 'NCBI Datasets' => [400, '{"error":"Bad Request","code":400,"message":"Invalid argument type (\'parameter: gene_ids\') provided."}', 'Invalid argument type (\'parameter: gene_ids\') provided.'];
        yield 'JPL' => [400, '{"moreInfo":"https://ssd-api.jpl.nasa.gov/doc/sbdb.html","code":"400","message":"one or more query parameter was not recognized"}', 'one or more query parameter was not recognized'];
        yield 'ChEMBL' => [400, '{"error_message": "\'CharField\' object has no attribute \'get_related_resource\'", "traceback": "Traceback (most recent call last): ..."}', '\'CharField\' object has no attribute \'get_related_resource\''];
        yield 'Ensembl' => [400, '{"error":"ID \'ENSG00000000000\' not found"}', 'ID \'ENSG00000000000\' not found'];
        yield 'Wikidata' => [400, '{"error":{"code":"no-such-entity","info":"Could not find an entity with the ID \"Qbad\".","id":"Qbad"},"servedby":"mw-api-ext"}', 'Could not find an entity with the ID "Qbad".'];
        yield 'GBIF, text' => [400, 'Max offset of 100001 exceeded: 200000 + 1', 'Max offset of 100001 exceeded: 200000 + 1'];
        yield 'GBIF, an HTML page' => [400, "<!DOCTYPE html>\n<html>\n  <head>\n    <title>400 Unrecognized checklistKey.  No matching service available for this checklistKey.</title>\n  </head>\n  <body>\n    <h1>Error 400 Unrecognized checklistKey.</h1>\n    <h3>Guru Meditation:</h3>\n  </body>\n</html>", '400 Unrecognized checklistKey. No matching service available for this checklistKey.'];
        yield 'USGS, text' => [400, "Error 400: Bad Request\n\nBad starttime value \"bad\". Valid values are ISO-8601 timestamps.\n\nUsage details are available from https://earthquake.usgs.gov/fdsnws/event/1\n", 'Error 400: Bad Request Bad starttime value "bad". Valid values are ISO-8601 timestamps. Usage details are available from https://earthquake.usgs.gov/fdsnws/event/1'];
        yield 'SIMBAD, a VOTable' => [400, "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<VOTABLE version=\"1.3\">\n<RESOURCE type=\"results\">\n<INFO name=\"QUERY_STATUS\" value=\"ERROR\">Incorrect ADQL query: 1 unresolved identifiers: nope [l.1 c.8 - l.1 c.12]!\n  - Unknown column \"nope\" !</INFO>\n<INFO name=\"PROVIDER\" value=\"CDS\">SIMBAD TAP Service</INFO>\n</RESOURCE>\n</VOTABLE>", 'Incorrect ADQL query: 1 unresolved identifiers: nope [l.1 c.8 - l.1 c.12]! - Unknown column "nope" !'];
        yield 'JSON with none of the forms' => [400, '{"status":"bad"}', '{"status":"bad"}'];
    }

    #[DataProvider('answers')]
    public function testWhatTheServiceSaid(int $status, string $body, string $said): void
    {
        $source = new class(new MockHttpClient(new MockResponse($body, ['http_code' => $status])), 'https://example.org/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }

            public function read(): ?array
            {
                return $this->getJson('anything');
            }
        };

        try {
            $source->read();
            self::fail('an error answered');
        } catch (ProviderException $e) {
            self::assertSame([$status, '[example] the service answered '.$status.': '.$said], [$e->status, $e->getMessage()]);
        }
    }

    public function testAMaintenancePageIsUnavailable(): void
    {
        $source = new class(new MockHttpClient(new MockResponse("<!DOCTYPE html>\n<html class=\"lt-ie9\"><head><title>Gaia (OPER) Downtime Page</title></head><body>Maintenance ongoing!</body></html>")), 'https://example.org/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }

            public function read(): ?array
            {
                return $this->getJson('anything');
            }
        };
        $this->expectException(UnavailableException::class);
        $this->expectExceptionMessage('[example] it answered a web page, not JSON: Gaia (OPER) Downtime Page');
        $source->read();
    }

    public function testALongTextIsCut(): void
    {
        $source = new class(new MockHttpClient([new MockResponse(str_repeat('a ', 400), ['http_code' => 400]), new MockResponse('{"errors":["down"]}', ['http_code' => 503])]), 'https://example.org/') extends HttpSource {
            public function getName(): string
            {
                return 'example';
            }

            public function capabilities(): array
            {
                return [];
            }

            public function read(): ?array
            {
                return $this->getJson('anything');
            }
        };
        try {
            $source->read();
            self::fail('an error answered');
        } catch (ProviderException $e) {
            self::assertSame(300, \strlen(substr($e->getMessage(), \strlen('[example] the service answered 400: '))));
        }
        $this->expectException(UnavailableException::class);
        $source->read();
    }
}
