<?php

declare(strict_types=1);

namespace Modules\JourneyMap;

/**
 * One place of a post on the journey, checked and rounded the same way whether it comes
 * from the editor or from a backup.
 */
final class Stop
{
    private const NAME_MAX = 160;

    private const STOP_MAX = 9999;

    /** Two decimals is about 1.1 km, so an approximate place never keeps the exact spot. */
    private const AREA_DECIMALS = 2;

    private const EXACT_DECIMALS = 6;

    private function __construct(
        public readonly string $placeName,
        public readonly float $lat,
        public readonly float $lng,
        public readonly string $precision,
        public readonly ?int $stopNumber,
    ) {}

    /**
     * Build a stop from untrusted input with the keys place_name, lat, lng, precision and stop_number.
     *
     * @param  array<string, mixed>  $input
     *
     * @throws InvalidStopException Listing every field that is wrong
     */
    public static function fromInput(array $input): self
    {
        $name = trim(is_string($input['place_name'] ?? null) ? $input['place_name'] : '');
        $lat = self::coordinate($input['lat'] ?? null, 90);
        $lng = self::coordinate($input['lng'] ?? null, 180);
        $precision = $input['precision'] ?? null;
        $stopNumber = self::stopNumber($input['stop_number'] ?? null);

        $errors = array_filter([
            'place_name' => $name === '' || mb_strlen($name) > self::NAME_MAX ? 'journey-map::errors.placeName' : null,
            'lat' => $lat === null ? 'journey-map::errors.lat' : null,
            'lng' => $lng === null ? 'journey-map::errors.lng' : null,
            'precision' => !in_array($precision, ['exact', 'area'], true) ? 'journey-map::errors.precision' : null,
            'stop_number' => $stopNumber === false ? 'journey-map::errors.stopNumber' : null,
        ]);
        if ($errors !== []) {
            throw new InvalidStopException($errors);
        }

        $decimals = $precision === 'area' ? self::AREA_DECIMALS : self::EXACT_DECIMALS;

        return new self($name, round($lat, $decimals), round($lng, $decimals), $precision, $stopNumber ?: null);
    }

    /**
     * The stop as journey_stops columns.
     *
     * @return array{place_name: string, lat: float, lng: float, place_precision: string, stop_number: ?int}
     */
    public function toRow(): array
    {
        return [
            'place_name' => $this->placeName,
            'lat' => $this->lat,
            'lng' => $this->lng,
            'place_precision' => $this->precision,
            'stop_number' => $this->stopNumber,
        ];
    }

    /**
     * A number within plus or minus the limit, or null when the input is not one.
     */
    private static function coordinate(mixed $value, int $limit): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        return $number >= -$limit && $number <= $limit ? $number : null;
    }

    /**
     * @return int|null|false The number, null when left empty, false when it is not a usable number
     */
    private static function stopNumber(mixed $value): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        $range = ['options' => ['min_range' => 1, 'max_range' => self::STOP_MAX]];
        $number = filter_var($value, FILTER_VALIDATE_INT, $range);

        return $number === false ? false : $number;
    }
}
