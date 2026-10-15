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
 *   new Quantity(1.67262192595e-27, 'kg', 5.2e-37); // "1.67262192595e-27 ± 0.00000000052e-27 kg"
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

    /**
     * The number as written for a reader: "194.19 g/mol", "4.6 ± 0.2 km";
     * a float below 1e-4 or from 1e9 up in scientific notation with every
     * significant digit it holds, its uncertainty in the same exponent, as
     * CODATA writes them: "1.67262192595e-27 ± 0.00000000052e-27 kg". An
     * integer as it is; an uncertainty a fixed notation would round to 0
     * in scientific notation of its own.
     */
    public function __toString(): string
    {
        $scientific = \is_float($this->value) && is_finite($this->value) && 0.0 !== $this->value && (abs($this->value) < 1e-4 || abs($this->value) >= 1e9);
        if ($scientific) {
            [$digits, $exponent] = self::digits(abs($this->value));
            $value = ($this->value < 0 ? '-' : '').self::decimal($digits, 0).'e'.$exponent;
            if (null !== $this->uncertainty) {
                $value .= ' ± '.(0.0 === $this->uncertainty || !is_finite($this->uncertainty) ? (string) $this->uncertainty : self::decimal(...self::shifted(abs($this->uncertainty), $exponent)).'e'.$exponent);
            }
        } else {
            $value = \is_float($this->value) ? self::fixed($this->value) : (string) $this->value;
            if (null !== $this->uncertainty) {
                $fixed = self::fixed($this->uncertainty);
                if ('0' === $fixed && 0.0 !== $this->uncertainty && is_finite($this->uncertainty)) {
                    [$digits, $exponent] = self::digits(abs($this->uncertainty));
                    $fixed = self::decimal($digits, 0).'e'.$exponent;
                }
                $value .= ' ± '.$fixed;
            }
        }

        return null === $this->unit || '' === $this->unit ? $value : $value.' '.$this->unit;
    }

    /** Ten decimals at most, the trailing zeros dropped: the notation of ordinary magnitudes. */
    private static function fixed(float $number): string
    {
        return rtrim(rtrim(\sprintf('%.10F', $number), '0'), '.');
    }

    /**
     * The significant digits of a positive finite float, the fewest that
     * give it back, and the exponent of the first: 1.67262192595e-27 is
     * ["167262192595", -27].
     *
     * @return array{string, int}
     */
    private static function digits(float $number): array
    {
        for ($precision = 0; $precision < 17; ++$precision) {
            $written = \sprintf('%.'.$precision.'e', $number);
            if ((float) $written === $number) {
                break;
            }
        }
        [$mantissa, $exponent] = explode('e', $written);
        $digits = rtrim(str_replace('.', '', $mantissa), '0');

        return ['' === $digits ? '0' : $digits, (int) $exponent];
    }

    /**
     * A positive float's digits placed against another exponent:
     * 5.2e-37 against -27 is ["52", -10], written 0.00000000052.
     *
     * @return array{string, int}
     */
    private static function shifted(float $number, int $exponent): array
    {
        [$digits, $own] = self::digits($number);

        return [$digits, $own - $exponent];
    }

    /** Digits whose first stands at 10^$exponent, written in decimals: ("52", -10) is 0.00000000052, ("15", 1) is 15. */
    private static function decimal(string $digits, int $exponent): string
    {
        if ($exponent < 0) {
            return '0.'.str_repeat('0', -$exponent - 1).$digits;
        }
        $digits = str_pad($digits, $exponent + 1, '0');
        $fraction = substr($digits, $exponent + 1);

        return substr($digits, 0, $exponent + 1).('' === $fraction ? '' : '.'.$fraction);
    }
}
