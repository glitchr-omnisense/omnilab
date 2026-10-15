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
 * What the sources over HTTP share: the calls, their errors mapped the same
 * way (404 is null; 429 a RateLimitedException; 5xx and transport errors an
 * UnavailableException; any other 4xx a ProviderException), a minimum
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
        $answer = $this->fetch($path, $query, ['Accept' => 'application/json'] + $headers);
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
        return $this->fetch($path, $query, $headers)[0] ?? null;
    }

    /**
     * The body and the headers of a GET; null on 404 / 410.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $headers
     *
     * @return array{0: string, 1: array<string, list<string>>}|null
     */
    private function fetch(string $path, array $query, array $headers): ?array
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
            $response = $this->http->request('GET', $url, ['headers' => $headers + $this->headers]);
            $status = $response->getStatusCode();
            if (404 === $status || 410 === $status) {
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

    /** What an error answer says: its JSON message when it has one (reason, message, detail, PubChem's Fault), else its text, cut. */
    private static function reason(string $body): string
    {
        $data = json_decode($body, true);
        if (\is_array($data)) {
            foreach ([$data['reason'] ?? null, $data['detail']['message'] ?? null, $data['detail'] ?? null, $data['Fault']['Message'] ?? null, $data['message'] ?? null, $data['error'] ?? null] as $message) {
                if (\is_string($message) && '' !== $message) {
                    $details = $data['Fault']['Details'] ?? [];

                    return $message.(\is_array($details) && $details ? ' ('.implode('; ', array_filter($details, 'is_string')).')' : '');
                }
            }
        }

        return (string) preg_replace('~^(.{0,300}).*$~us', '$1', trim(strip_tags($body)));
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
