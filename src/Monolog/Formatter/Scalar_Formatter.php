<?php

declare (strict_types=1);
/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Monolog\Formatter;

use Monolog\Log_Record;
/**
 * Formats data into an associative array of scalar (+ null) values.
 * Objects and arrays will be JSON encoded.
 *
 * @author Andrew Lawson <adlawson@gmail.com>
 */
class Scalar_Formatter extends Normalizer_Formatter
{
    /**
     * @inheritDoc
     *
     * @phpstan-return array<string, scalar|null> $record
     */
    public function format(Log_Record $record): array
    {
        $result = [];
        foreach ($record->to_array() as $key => $value) {
            $result[$key] = $this->to_scalar($value);
        }
        return $result;
    }
    protected function to_scalar(mixed $value): string|int|float|bool|null
    {
        $normalized = $this->normalize($value);
        if (\is_array($normalized)) {
            return $this->to_json($normalized, true);
        }
        return $normalized;
    }
}