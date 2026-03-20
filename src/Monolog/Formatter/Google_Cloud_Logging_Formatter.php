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

use DateTimeInterface;
use Monolog\Log_Record;
/**
 * Encodes message information into JSON in a format compatible with Cloud logging.
 *
 * @see https://cloud.google.com/logging/docs/structured-logging
 * @see https://cloud.google.com/logging/docs/reference/v2/rest/v2/LogEntry
 *
 * @author Luís Cobucci <lcobucci@gmail.com>
 */
class Google_Cloud_Logging_Formatter extends Json_Formatter
{
    protected function normalize_record(Log_Record $record): array
    {
        $normalized = parent::normalize_record($record);
        // Re-key level for GCP logging
        $normalized['severity'] = $normalized['level_name'];
        $normalized['time'] = $record->datetime->format(DateTimeInterface::RFC3339_EXTENDED);
        // Remove keys that are not used by GCP
        unset($normalized['level'], $normalized['level_name'], $normalized['datetime']);
        return $normalized;
    }
}