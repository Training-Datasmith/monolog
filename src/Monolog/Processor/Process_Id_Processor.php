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
 * Adds value of getmypid into records
 *
 * @author Andreas Hörnicke
 */
class Process_Id_Processor implements Processor_Interface
{
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        $record->extra['process_id'] = getmypid();
        return $record;
    }
}