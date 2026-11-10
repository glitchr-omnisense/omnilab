<?php

namespace Omnilab\Tests\Policy;

use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnisense\Contracts\Policy\UsagePolicy;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** A source over HTTP that reads {"title": ...} by id, declaring the policy it is given. */
final class HttpStubSource extends HttpSource
{
    public function __construct(HttpClientInterface $http, private readonly ?UsagePolicy $policy = null, float $throttle = 0.0)
    {
        parent::__construct($http, 'https://stub.example.org/', [], $throttle);
    }

    public function getName(): string
    {
        return 'http-stub';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY];
    }

    public function usagePolicy(): UsagePolicy
    {
        return $this->policy ?? parent::usagePolicy();
    }

    public function title(string $id): ?string
    {
        $data = $this->getJson('things/'.$id);

        return null === $data ? null : (\is_string($data['title'] ?? null) ? $data['title'] : '');
    }

    /** A request the source sends itself, through governed(). */
    public function raw(string $id): int
    {
        $this->governed('GET', 'https://stub.example.org/files/'.$id);

        return $this->http->request('GET', 'https://stub.example.org/files/'.$id)->getStatusCode();
    }
}
