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
 * Encodes message information into JSON in a format compatible with Loggly.
 *
 * @author Adam Pancutt <adam@pancutt.com>
 */
class Loggly_Formatter extends Json_Formatter
{
    /**
     * Overrides the default batch mode to new lines for compatibility with the
     * Loggly bulk API.
     */
    public function __construct(int $batch_mode = self::BATCH_MODE_NEWLINES, bool $append_newline = false)
    {
        parent::__construct($batch_mode, $append_newline);
    }
    /**
     * Appends the 'timestamp' parameter for indexing by Loggly.
     *
     * @see https://www.loggly.com/docs/automated-parsing/#json
     * @see \Monolog\Formatter\JsonFormatter::format()
     */
    protected function normalize_record(Log_Record $record): array
    {
        $record_data = parent::normalize_record($record);
        $record_data['timestamp'] = $record->datetime->format("Y-m-d\\TH:i:s.uO");
        unset($record_data['datetime']);
        return $record_data;
    }
}