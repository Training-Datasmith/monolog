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
namespace Monolog\Processor;

use Monolog\Log_Record;
/**
 * Injects memory_get_usage in all records
 *
 * @see Monolog\Processor\MemoryProcessor::__construct() for options
 * @author Rob Jensen
 */
class Memory_Usage_Processor extends Memory_Processor
{
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        $usage = memory_get_usage($this->real_usage);
        if ($this->use_formatting) {
            $usage = $this->format_bytes($usage);
        }
        $record->extra['memory_usage'] = $usage;
        return $record;
    }
}