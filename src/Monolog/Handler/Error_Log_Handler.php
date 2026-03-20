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
namespace Monolog\Handler;

use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Line_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Stores to PHP error_log() handler.
 *
 * @author Elan Ruusamäe <glen@delfi.ee>
 */
class Error_Log_Handler extends Abstract_Processing_Handler
{
    public const OPERATING_SYSTEM = 0;
    public const SAPI = 4;
    /** @var 0|4 */
    protected int $message_type;
    /**
     * @param 0|4 $messageType    Says where the error should go.
     * @param bool $expandNewlines If set to true, newlines in the message will be expanded to be take multiple log entries
     *
     * @throws \InvalidArgumentException If an unsupported message type is set
     */
    public function __construct(int $message_type = self::OPERATING_SYSTEM, int|string|Level $level = Level::Debug, bool $bubble = true, protected bool $expand_newlines = false)
    {
        parent::__construct($level, $bubble);
        if (false === \in_array($message_type, self::get_available_types(), true)) {
            $message = sprintf('The given message type "%s" is not supported', print_r($message_type, true));
            throw new \InvalidArgumentException($message);
        }
        $this->message_type = $message_type;
    }
    /**
     * @return int[] With all available types
     */
    public static function get_available_types(): array
    {
        return [self::OPERATING_SYSTEM, self::SAPI];
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Line_Formatter('[%datetime%] %channel%.%level_name%: %message% %context% %extra%');
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        if (!$this->expand_newlines) {
            error_log((string) $record->formatted, $this->message_type);
            return;
        }
        $lines = preg_split('{[\r\n]+}', (string) $record->formatted);
        if ($lines === false) {
            $pcre_error_code = preg_last_error();
            throw new \RuntimeException('Failed to preg_split formatted string: ' . $pcre_error_code . ' / ' . preg_last_error_msg());
        }
        foreach ($lines as $line) {
            error_log($line, $this->message_type);
        }
    }
}