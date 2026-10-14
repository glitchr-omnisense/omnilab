<?php

namespace Omnilab\Model;

/**
 * One thing known of an entity: a key that does not change from one source
 * to the next when they mean the same ("molecular_weight", "magnitude",
 * "depth"), a label to show, a value - a Quantity when it has a unit, else
 * a text, a number or a boolean - and the source that said it.
 *
 *   new Property('molecular_weight', 'Molecular weight', new Quantity(194.19, 'g/mol'), 'pubchem');
 *   new Property('molecular_formula', 'Molecular formula', 'C8H10N4O2', 'pubchem');
 */
final readonly class Property implements \Stringable
{
    public function __construct(
        public string $key,
        public string $label,
        public Quantity|string|int|float|bool $value,
        public ?string $source = null,
    ) {
    }

    /** The number, a Quantity's included; null for a text or a boolean. */
    public function number(): int|float|null
    {
        return match (true) {
            $this->value instanceof Quantity => $this->value->value,
            \is_int($this->value), \is_float($this->value) => $this->value,
            default => null,
        };
    }

    /** A Quantity's unit; null otherwise. */
    public function unit(): ?string
    {
        return $this->value instanceof Quantity ? $this->value->unit : null;
    }

    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    /** The value as text: "194.19 g/mol", "C8H10N4O2", "yes". */
    public function __toString(): string
    {
        return match (true) {
            \is_bool($this->value) => $this->value ? 'yes' : 'no',
            \is_float($this->value) => (string) new Quantity($this->value),
            default => (string) $this->value,
        };
    }
}
