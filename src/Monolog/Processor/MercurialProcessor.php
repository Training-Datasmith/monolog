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

use Monolog\Level;
use Monolog\Logger;
use Monolog\Log_Record;
use Psr\Log\Log_Level;
/**
 * Injects Hg branch and Hg revision number in all records
 *
 * @author Jonathan A. Schweder <jonathanschweder@gmail.com>
 */
class Mercurial_Processor implements Processor_Interface
{
    private readonly Level $level;
    /** @var array{branch: string, revision: string}|array<never>|null */
    private static ?array $cache = null;
    /**
     * @param int|string|Level $level The minimum logging level at which this Processor will be triggered
     *
     * @phpstan-param value-of<Level::VALUES>|value-of<Level::NAMES>|Level|LogLevel::* $level
     */
    public function __construct(int|string|Level $level = Level::Debug)
    {
        $this->level = Logger::to_monolog_level($level);
    }
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        // return if the level is not high enough
        if ($record->level->is_lower_than($this->level)) {
            return $record;
        }
        $record->extra['hg'] = self::get_mercurial_info();
        return $record;
    }
    /**
     * @return array{branch: string, revision: string}|array<never>
     */
    private static function get_mercurial_info(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $result = explode(' ', trim((string) shell_exec('hg id -nb')));
        if (\count($result) >= 3) {
            return self::$cache = ['branch' => $result[1], 'revision' => $result[2]];
        }
        if (\count($result) === 2) {
            return self::$cache = ['branch' => $result[1], 'revision' => $result[0]];
        }
        return self::$cache = [];
    }
}