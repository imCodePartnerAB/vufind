<?php

/**
 * Swedish personal identity number (personnummer / samordningsnummer).
 *
 * PHP version 7.4+
 *
 * @category LOTS
 * @package  BankID
 */

namespace LOTS\BankID;

/**
 * Validated, normalised personnummer.
 *
 * Accepts YYYYMMDDNNNN, YYYYMMDD-NNNN, YYMMDDNNNN, YYMMDD-NNNN and YYMMDD+NNNN
 * (the "+" marks people aged 100 or more). Samordningsnummer (day + 60) are
 * accepted. The check digit (Luhn over the 10-digit form) is verified.
 */
final class Personnummer
{
    public const FORMAT_12 = 'YYYYMMDDNNNN';
    public const FORMAT_12_DASH = 'YYYYMMDD-NNNN';
    public const FORMAT_10 = 'YYMMDDNNNN';
    public const FORMAT_10_DASH = 'YYMMDD-NNNN';

    public const FORMATS = [
        self::FORMAT_12, self::FORMAT_12_DASH, self::FORMAT_10, self::FORMAT_10_DASH,
    ];

    /**
     * Normalised value, 12 digits (YYYYMMDDNNNN).
     *
     * @var string
     */
    private $digits;

    /**
     * Constructor
     *
     * @param string $digits 12 validated digits
     */
    private function __construct(string $digits)
    {
        $this->digits = $digits;
    }

    /**
     * Parse and validate.
     *
     * @param string                  $raw   Input
     * @param \DateTimeImmutable|null $today Reference date for 10-digit input
     *
     * @throws \InvalidArgumentException
     * @return self
     */
    public static function fromString(
        string $raw,
        ?\DateTimeImmutable $today = null
    ): self {
        $value = preg_replace('/\s+/', '', $raw);
        if (!preg_match('/^(\d{2})?(\d{6})([-+]?)(\d{4})$/', $value, $m)) {
            throw new \InvalidArgumentException('Invalid personnummer format');
        }
        [, $century, $yymmdd, $separator, $last4] = $m;
        if ('' !== $century && '+' === $separator) {
            throw new \InvalidArgumentException('Invalid personnummer format');
        }

        if ('' === $century) {
            $century = self::guessCentury(
                (int)substr($yymmdd, 0, 2),
                '+' === $separator,
                $today ?? new \DateTimeImmutable('today')
            );
        }

        $year = (int)($century . substr($yymmdd, 0, 2));
        $month = (int)substr($yymmdd, 2, 2);
        $day = (int)substr($yymmdd, 4, 2);
        if ($day > 60) {
            $day -= 60; // samordningsnummer
        }
        if (!checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException('Invalid personnummer date');
        }
        if (!self::luhnValid($yymmdd . $last4)) {
            throw new \InvalidArgumentException('Invalid personnummer check digit');
        }

        return new self($century . $yymmdd . $last4);
    }

    /**
     * Format for storage/lookup.
     *
     * @param string $format One of the FORMAT_* constants
     *
     * @throws \InvalidArgumentException
     * @return string
     */
    public function format(string $format): string
    {
        switch ($format) {
            case self::FORMAT_12:
                return $this->digits;
            case self::FORMAT_12_DASH:
                return substr($this->digits, 0, 8) . '-' . substr($this->digits, 8);
            case self::FORMAT_10:
                return substr($this->digits, 2);
            case self::FORMAT_10_DASH:
                return substr($this->digits, 2, 6) . '-' . substr($this->digits, 8);
        }
        throw new \InvalidArgumentException("Unknown personnummer format '$format'");
    }

    /**
     * Masked value for logs: birth date only.
     *
     * @return string
     */
    public function masked(): string
    {
        return substr($this->digits, 0, 8) . '-****';
    }

    /**
     * Value equality.
     *
     * @param self $other Other
     *
     * @return bool
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->digits, $other->digits);
    }

    /**
     * Century for a two-digit year: the most recent year not in the future,
     * minus 100 years when the "+" separator is used.
     *
     * @param int                $yy      Two-digit year
     * @param bool               $plus    "+" separator used
     * @param \DateTimeImmutable $today   Reference date
     *
     * @return string Two digits
     */
    private static function guessCentury(
        int $yy,
        bool $plus,
        \DateTimeImmutable $today
    ): string {
        $currentYear = (int)$today->format('Y');
        $year = intdiv($currentYear, 100) * 100 + $yy;
        if ($year > $currentYear) {
            $year -= 100;
        }
        if ($plus) {
            $year -= 100;
        }
        return (string)intdiv($year, 100);
    }

    /**
     * Luhn check over the 10-digit form.
     *
     * @param string $tenDigits Digits
     *
     * @return bool
     */
    private static function luhnValid(string $tenDigits): bool
    {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $d = (int)$tenDigits[$i] * (0 === $i % 2 ? 2 : 1);
            $sum += $d > 9 ? $d - 9 : $d;
        }
        return 0 === $sum % 10;
    }
}
