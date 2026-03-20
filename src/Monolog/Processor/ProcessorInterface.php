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
 * An optional interface to allow labelling Monolog processors.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
interface Processor_Interface
{
    /**
     * @return LogRecord The processed record
     */
    public function __invoke(Log_Record $record);
}