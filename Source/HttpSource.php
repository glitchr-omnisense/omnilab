<?php

namespace Omnilab\Source;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Exception\RateLimitedException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Scheme;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * What the sources over HTTP share: the calls (GET, and POST for the
 * services that take a query as a JSON body), their errors mapped the same
 * way (404 and 204 are null; 429 a RateLimitedException; 5xx and transport
 * errors an UnavailableException; any other 4xx a ProviderException), a minimum
 * interval between two calls when the service asks for one (PubChem: five
 * calls a second at most), and the reading of identifiers given as strings.
 * What a source does not override it does not do: NotSupportedException.
 */
abstract class HttpSource implements SourceInterface
{
    private float $lastCall = 0.0;

    /**
     * @param array<string, string> $headers  sent with every call (User-Agent...)
     * @param float                 $throttle seconds to leave between two calls
     */
    public function __construct(
        protected readonly HttpClientInterface $http,
        protected readonly string $baseUri,
        protected readonly array $headers = [],
        protected readonly float $throttle = 0.0,
    ) {
    }

    public function entity(Identifier|string $id): ?Entity
    {
        throw NotSupportedException::operation($this->getName(), 'look up entities');
    }

    public function search(Query $query): Page
    {
        throw NotSupportedException::operation($this->getName(), 'search');
    }

    public function series(SeriesQuery $query): array
    {
        throw NotSupportedException::operation($this->getName(), 'serve series');
    }

    public function products(ProductQuery $query): Page
    {
        throw NotSupportedException::operation($this->getName(), 'list products');
    }

    public function occurrences(OccurrenceQuery $query): Page
    {
        throw NotSupportedException::operation($this->getName(), 'list occurrences');
    }

    /**
     * The station a series query names in place of a Point or a BBox, when
     * it is of one of the schemes the source reads; null when the query
     * names a place. A station of any other scheme is refused before any
     * call (a source that reads none calls it without a scheme).
     */
    protected function station(SeriesQuery $query, Scheme ...$schemes): ?Identifier
    {
        if (!$query->place instanceof Identifier) {
            return null;
        }
        if (!\in_array($query->place->scheme, $schemes, true)) {
            throw NotSupportedException::operation($this->getName(), \sprintf('serve the records of a station of %s (%s)', $query->place->scheme->label(), $schemes ? 'it reads '.implode(', ', array_map(static fn (Scheme $s) => $s->label(), $schemes)) : 'a place: a Point'));
        }

        return $query->place;
    }

    /**
     * The filters of an occurrence query, when the source reads every one
     * (an empty value left out); a key it does not read is refused before
     * any call, rather than answered unfiltered.
     *
     * @return array<string, mixed>
     */
    protected function filters(OccurrenceQuery $query, string ...$known): array
    {
        $unknown = array_diff(array_keys($query->filters), $known);
        if ($unknown) {
            throw NotSupportedException::operation($this->getName(), \sprintf('filter occurrences by "%s" (it reads %s)', implode('", "', $unknown), $known ? '"'.implode('", "', $known).'"' : 'none'));
        }

        return array_filter($query->filters, static fn (mixed $value) => null !== $value && '' !== $value && [] !== $value);
    }

    /**
     * GETs JSON. Null when the service answers 404 / 410.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    protected function getJson(string $path, array $query = [], array $headers = []): ?array
    {
        return $this->getJsonWithHeaders($path, $query, $headers)[0] ?? null;
    }

    /**
     * GETs JSON with the answer's headers, for a service that pages or
     * counts in them (a Link to the next page, a total): [the data, the
     * headers by lower-case name]. Null when the service answers 404 / 410.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     *
     * @return array{0: array<mixed>, 1: array<string, list<string>>}|null
     */
    protected function getJsonWithHeaders(string $path, array $query = [], array $headers = []): ?array
    {
        return $this->decode($this->fetch('GET', $path, $query, ['Accept' => 'application/json'] + $headers));
    }

    /**
     * POSTs a JSON body and reads JSON, for a service that takes its query
     * so (a GraphQL endpoint, a search API, a batch lookup). Null when the
     * service answers 404 / 410, or 204 (no content: nothing matched).
     *
     * @param array<mixed>          $body  sent as JSON
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    protected function postForJson(string $path, array $body, array $query = [], array $headers = []): ?array
    {
        try {
            $json = json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('The body cannot be written as JSON: '.$e->getMessage(), 0, $e);
        }

        return $this->decode($this->fetch('POST', $path, $query, ['Accept' => 'application/json', 'Content-Type' => 'application/json'] + $headers, $json))[0] ?? null;
    }

    /**
     * @param array{0: string, 1: array<string, list<string>>}|null $answer
     *
     * @return array{0: array<mixed>, 1: array<string, list<string>>}|null
     */
    private function decode(?array $answer): ?array
    {
        if (null === $answer) {
            return null;
        }
        try {
            $data = json_decode($answer[0], true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ProviderException($this->getName(), 'the answer is not JSON: '.$e->getMessage(), null, $e);
        }

        return \is_array($data) ? [$data, $answer[1]] : throw new ProviderException($this->getName(), 'the answer is not a JSON object');
    }

    /**
     * GETs a body as text. Null when the service answers 404 / 410.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     */
    protected function get(string $path, array $query = [], array $headers = []): ?string
    {
        return $this->fetch('GET', $path, $query, $headers)[0] ?? null;
    }

    /**
     * The body and the headers of a call; null on 404 / 410, and on 204.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     *
     * @return array{0: string, 1: array<string, list<string>>}|null
     */
    private function fetch(string $method, string $path, array $query, array $headers, ?string $body = null): ?array
    {
        $this->wait();
        $url = str_starts_with($path, 'http') ? $path : rtrim($this->baseUri, '/').'/'.ltrim($path, '/');
        $pairs = [];
        foreach ($query as $key => $values) {
            // A list repeats its key; null and empty are left out.
            foreach (\is_array($values) ? $values : [$values] as $value) {
                if (null !== $value && '' !== $value) {
                    $pairs[] = rawurlencode((string) $key).'='.rawurlencode(\is_bool($value) ? ($value ? 'true' : 'false') : (string) $value);
                }
            }
        }
        if ($pairs) {
            $url .= (str_contains($url, '?') ? '&' : '?').implode('&', $pairs);
        }
        try {
            $response = $this->http->request($method, $url, ['headers' => $headers + $this->headers] + (null === $body ? [] : ['body' => $body]));
            $status = $response->getStatusCode();
            if (404 === $status || 410 === $status || 204 === $status) {
                return null;
            }
            if (429 === $status) {
                $retry = $response->getHeaders(false)['retry-after'][0] ?? null;
                throw new RateLimitedException($this->getName(), null !== $retry && is_numeric($retry) ? (int) $retry : null);
            }
            if ($status >= 500) {
                throw new UnavailableException($this->getName(), \sprintf('the service answered %d', $status), $status);
            }
            if ($status >= 400) {
                throw new ProviderException($this->getName(), \sprintf('the service answered %d: %s', $status, self::reason($response->getContent(false))), $status);
            }

            // A redirection the client did not follow (MockHttpClient, max_redirects 0) gives its own body.
            return [$response->getContent(false), $response->getHeaders(false)];
        } catch (TransportExceptionInterface $e) {
            throw new UnavailableException($this->getName(), 'unreachable: '.$e->getMessage(), null, $e);
        } catch (ExceptionInterface $e) {
            throw new ProviderException($this->getName(), $e->getMessage(), null, $e);
        }
    }

    /**
     * What an error answer says: its JSON message in whichever form the
     * service writes it (docs/sources.md#errors lists them), else an HTML
     * page's title or a VOTable's error, else its text - white space
     * collapsed, cut at 300 characters.
     */
    private static function reason(string $body): string
    {
        $data = json_decode($body, true);
        if (\is_array($data) && null !== ($message = self::message($data))) {
            return $message;
        }
        $text = match (true) {
            // SIMBAD's TAP: a VOTable whose QUERY_STATUS says the error.
            (bool) preg_match('~<INFO[^>]*name="QUERY_STATUS"[^>]*value="ERROR"[^>]*>(.*?)</INFO>~is', $body, $m) => $m[1],
            // An error page (GBIF's Varnish, the EBI's): its title.
            (bool) preg_match('~<title[^>]*>(.*?)</title>~is', $body, $m) && '' !== trim(strip_tags($m[1])) => $m[1],
            default => $body,
        };
        $text = trim((string) preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8')));

        return (string) preg_replace('~^(.{0,300}).*$~us', '$1', $text);
    }

    /**
     * The message of a JSON error answer, its details in parentheses; null
     * when it has none of the forms the services write.
     *
     * @param array<mixed> $data
     */
    private static function message(array $data): ?string
    {
        $text = static fn (mixed $value): ?string => \is_string($value) && '' !== trim($value) ? trim($value) : null;
        // A list of strings (the CMR's errors, UniProt's messages) or of objects with a message and what it is about (NCEI's errors, the NWS's parameterErrors, iNaturalist's v2).
        $list = static function (mixed $items) use ($text): array {
            $messages = [];
            foreach (\is_array($items) ? $items : [] as $item) {
                if (null !== ($message = $text($item))) {
                    $messages[] = $message;
                } elseif (\is_array($item) && null !== ($message = $text($item['message'] ?? $item['reason'] ?? $item['info'] ?? null))) {
                    $about = $text($item['field'] ?? $item['parameter'] ?? null);
                    $messages[] = null === $about ? $message : $about.': '.$message;
                }
            }

            return $messages;
        };
        $title = $text($data['title'] ?? null);
        $detail = $text($data['detail'] ?? null);
        $error = $data['error'] ?? null;
        $main = $text($data['reason'] ?? null)                                                       // Open-Meteo
            ?? $text($data['Fault']['Message'] ?? null)                                              // PubChem
            ?? (null !== $title && null !== $detail ? ($title === $detail ? $title : $title.': '.$detail) : null) // RFC 9457: the CDS, the NWS
            ?? $text($data['detail']['message'] ?? null) ?? $detail ?? $title                        // the Copernicus STAC (FastAPI)
            ?? $text($data['description'] ?? null)                                                   // the Copernicus STAC (stac-fastapi)
            ?? $text($data['errorMessage'] ?? null)                                                  // NCEI
            ?? $text($data['message'] ?? null)                                                       // NCBI Datasets, JPL, RCSB
            ?? $text($data['error_message'] ?? null)                                                 // ChEMBL
            ?? $text($error)                                                                         // Ensembl, iNaturalist
            ?? (\is_array($error) ? $text($error['info'] ?? $error['message'] ?? null) : null);      // Wikidata
        $details = array_values(array_unique([
            ...$list($data['Fault']['Details'] ?? null),
            ...$list($data['parameterErrors'] ?? null),
            ...$list($data['errors'] ?? null),
            ...$list($data['messages'] ?? null),
        ]));
        if (null === $main) {
            return $details ? implode('; ', $details) : null;
        }
        $details = array_values(array_diff($details, [$main]));

        return $main.($details ? ' ('.implode('; ', $details).')' : '');
    }

    /** Leaves the configured interval since the last call. */
    private function wait(): void
    {
        if ($this->throttle > 0 && $this->lastCall > 0) {
            $left = $this->throttle - (microtime(true) - $this->lastCall);
            if ($left > 0) {
                usleep((int) ($left * 1_000_000));
            }
        }
        $this->lastCall = microtime(true);
    }

    /**
     * An identifier given as an Identifier or a string, when it is of one of
     * the schemes the source reads; null when the string is none of them
     * (a name, for the sources that look things up by name).
     */
    protected static function identify(Identifier|string $id, Scheme ...$schemes): ?Identifier
    {
        if ($id instanceof Identifier) {
            return \in_array($id->scheme, $schemes, true) ? $id : null;
        }
        $parsed = Identifier::parse($id);
        if (null !== $parsed && \in_array($parsed->scheme, $schemes, true)) {
            return $parsed;
        }
        foreach ($schemes as $scheme) {
            // A value whose shape does not tell its scheme (a bare CID, a GBIF key): the first scheme that reads it.
            if (null === $parsed && null !== ($identifier = Identifier::tryOf($scheme, $id))) {
                return $identifier;
            }
        }

        return null;
    }

    /** Strips tags and collapses the white space of a text from the service; null when nothing is left. */
    protected static function text(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }
        $text = html_entity_decode(strip_tags($text), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('~\s+~u', ' ', $text));

        return '' === $text ? null : $text;
    }

    /** A time the service wrote in ISO 8601 (RFC 3339); null when absent or unreadable. */
    protected static function time(?string $time): ?\DateTimeImmutable
    {
        if (null === $time || '' === $time) {
            return null;
        }
        try {
            return new \DateTimeImmutable($time);
        } catch (\Exception) {
            return null;
        }
    }
}
