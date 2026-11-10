<?php

namespace Omnilab;

use Omnilab\Exception\InvalidConfigException;

/**
 * A source's configuration while its factory builds it: the options
 * given, the factory's defaults under them, and the keys that must be filled.
 * omnisense/contracts' Config with the family's prefix ("omnilab.factory_name",
 * "omnilab.required_options", "omnilab.name", "omnilab.limiter"), its
 * messages and its InvalidConfigException; a null counts as not set.
 */
final class Config extends \Omnisense\Contracts\Provider\Config
{
    public const PREFIX = 'omnilab';

    /**
     * Sets each key not set yet (a null counts as not set).
     *
     * @param array<string, mixed> $defaults
     */
    public function defaults(array $defaults): static
    {
        foreach ($defaults as $key => $value) {
            if (!$this->offsetExists($key) || null === $this[$key]) {
                $this[$key] = $value;
            }
        }

        return $this;
    }

    /** @param string[] $keys */
    public function validateNotEmpty(array $keys): void
    {
        $missing = array_values(array_filter($keys, fn (string $key) => !isset($this[$key]) || '' === $this[$key] || [] === $this[$key]));
        if ($missing) {
            throw new InvalidConfigException(\sprintf('The "%s" source needs: %s.', $this['omnilab.factory_name'] ?? '?', implode(', ', $missing)));
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->offsetExists($key) && null !== $this[$key] ? $this[$key] : $default;
    }

    protected function invalid(string $message): \Throwable
    {
        return new InvalidConfigException(str_replace(' provider', ' source', $message));
    }
}
