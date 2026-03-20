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

use Monolog\Level;
use Monolog\Log_Record;
/**
 * Serializes a log message according to RFC 5424
 *
 * @author Dalibor Karlović <dalibor.karlovic@sigwin.hr>
 * @author Renat Gabdullin <renatobyj@gmail.com>
 */
class Syslog_Formatter extends Line_Formatter
{
    private const SYSLOG_FACILITY_USER = 1;
    private const FORMAT = "<%extra.priority%>1 %datetime% %extra.hostname% %extra.app-name% %extra.procid% %channel% %extra.structured-data% %level_name%: %message% %context% %extra%\n";
    private const NILVALUE = '-';
    private readonly string $hostname;
    private readonly int $procid;
    public function __construct(private readonly string $application_name = self::NILVALUE)
    {
        parent::__construct(self::FORMAT, 'Y-m-d\TH:i:s.uP', true, true);
        $this->hostname = (string) gethostname();
        $this->procid = (int) getmypid();
    }
    public function format(Log_Record $record): string
    {
        $record->extra = $this->format_extra($record);
        return parent::format($record);
    }
    /**
     * @return array<string, mixed>
     */
    private function format_extra(Log_Record $record): array
    {
        $extra = $record->extra;
        $extra['app-name'] = $this->application_name;
        $extra['hostname'] = $this->hostname;
        $extra['procid'] = $this->procid;
        $extra['priority'] = self::calculate_priority($record->level);
        $extra['structured-data'] = self::NILVALUE;
        return $extra;
    }
    private static function calculate_priority(Level $level): int
    {
        return self::SYSLOG_FACILITY_USER * 8 + $level->to_rfc5424level();
    }
}