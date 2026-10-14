<?php

namespace Omnilab\Model;

/**
 * A number with its unit, as the source gives it: "g/mol", "Å²", "°C",
 * "km/h", "mm", "kg/m³". No conversion is made: a unit is a label here, in
 * the spelling of the source that measured it (Open-Meteo's "°C", PubChem's
 * "g/mol"); null for a pure number.
 *
 *   new Quantity(194.19, 'g/mol');            // "194.19 g/mol"
 *   new Quantity(4.6, 'km', uncertainty: 0.2); // "4.6 ± 0.2 km"
 */
final readonly class Quantity implements \Stringable
{
    public function __construct(
        public int|float $value,
        public ?string $unit = null,
        public ?float $uncertainty = null,
    ) {
    }

    /** The same number in another unit, when the caller knows the factor: $metres->scaled(0.001, 'km'). */
    public function scaled(float $factor, ?string $unit): self
    {
        return new self($this->value * $factor, $unit, null === $this->uncertainty ? null : $this->uncertainty * $factor);
    }

    public function __toString(): string
    {
        $value = \is_float($this->value) ? rtrim(rtrim(\sprintf('%.10F', $this->value), '0'), '.') : (string) $this->value;
        if (null !== $this->uncertainty) {
            $value .= ' ± '.rtrim(rtrim(\sprintf('%.10F', $this->uncertainty), '0'), '.');
        }

        return null === $this->unit || '' === $this->unit ? $value : $value.' '.$this->unit;
    }
}
