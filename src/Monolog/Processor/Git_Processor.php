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
 * Injects Git branch and Git commit SHA in all records
 *
 * @author Nick Otter
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Git_Processor implements Processor_Interface
{
    private readonly Level $level;
    /** @var array{branch: string, commit: string}|array<never>|null */
    private static ?array $cache = null;
    /**
     * @param int|string|Level|LogLevel::* $level The minimum logging level at which this Processor will be triggered
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
        $record->extra['git'] = self::get_git_info();
        return $record;
    }
    /**
     * @return array{branch: string, commit: string}|array<never>
     */
    private static function get_git_info(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }
        $branches = shell_exec('git branch -v --no-abbrev');
        if (\is_string($branches) && 1 === preg_match('{^\* (.+?)\s+([a-f0-9]{40})(?:\s|$)}m', $branches, $matches)) {
            return self::$cache = ['branch' => $matches[1], 'commit' => $matches[2]];
        }
        return self::$cache = [];
    }
}