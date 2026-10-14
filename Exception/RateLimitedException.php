<?php

namespace Omnilab\Exception;

/** The service asks to slow down (429), or the day's budget is spent (PubChem's per-minute budget, Open-Meteo's daily one). */
final class RateLimitedException extends UnavailableException
{
    public function __construct(string $source, public readonly ?int $retryAfter = null, ?\Throwable $previous = null)
    {
        parent::__construct($source, $retryAfter ? \sprintf('rate limited, retry in %d s', $retryAfter) : 'rate limited', 429, $previous);
    }
}
