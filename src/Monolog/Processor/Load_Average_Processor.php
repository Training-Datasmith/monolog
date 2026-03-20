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
 * Injects sys_getloadavg in all records @see https://www.php.net/manual/en/function.sys-getloadavg.php
 *
 * @author Johan Vlaar <johan.vlaar.1994@gmail.com>
 */
class Load_Average_Processor implements Processor_Interface
{
    public const LOAD_1_MINUTE = 0;
    public const LOAD_5_MINUTE = 1;
    public const LOAD_15_MINUTE = 2;
    private const AVAILABLE_LOAD = [self::LOAD_1_MINUTE, self::LOAD_5_MINUTE, self::LOAD_15_MINUTE];
    protected int $avg_system_load;
    /**
     * @param self::LOAD_* $avgSystemLoad
     */
    public function __construct(int $avg_system_load = self::LOAD_1_MINUTE)
    {
        if (!\in_array($avg_system_load, self::AVAILABLE_LOAD, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid average system load: `%s`', $avg_system_load));
        }
        $this->avg_system_load = $avg_system_load;
    }
    /**
     * {@inheritDoc}
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        if (!\function_exists('sys_getloadavg')) {
            return $record;
        }
        $usage = sys_getloadavg();
        if (false === $usage) {
            return $record;
        }
        $record->extra['load_average'] = $usage[$this->avg_system_load];
        return $record;
    }
}