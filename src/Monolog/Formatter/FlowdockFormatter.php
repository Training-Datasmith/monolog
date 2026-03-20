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
 * formats the record to be used in the FlowdockHandler
 *
 * @author Dominik Liebler <liebler.dominik@gmail.com>
 * @deprecated Since 2.9.0 and 3.3.0, Flowdock was shutdown we will thus drop this handler in Monolog 4
 */
class Flowdock_Formatter implements Formatter_Interface
{
    public function __construct(private readonly string $source, private readonly string $source_email)
    {
    }
    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function format(Log_Record $record): array
    {
        $tags = ['#logs', '#' . $record->level->to_psr_log_level(), '#' . $record->channel];
        foreach ($record->extra as $value) {
            $tags[] = '#' . $value;
        }
        $subject = sprintf('in %s: %s - %s', $this->source, $record->level->get_name(), $this->get_short_message($record->message));
        return ['source' => $this->source, 'from_address' => $this->source_email, 'subject' => $subject, 'content' => $record->message, 'tags' => $tags, 'project' => $this->source];
    }
    /**
     * @inheritDoc
     *
     * @return mixed[][]
     */
    public function format_batch(array $records): array
    {
        $formatted = [];
        foreach ($records as $record) {
            $formatted[] = $this->format($record);
        }
        return $formatted;
    }
    public function get_short_message(string $message): string
    {
        static $has_mb_string;
        if (null === $has_mb_string) {
            $has_mb_string = \function_exists('mb_strlen');
        }
        $max_length = 45;
        if ($has_mb_string) {
            if (mb_strlen($message, 'UTF-8') > $max_length) {
                $message = mb_substr($message, 0, $max_length - 4, 'UTF-8') . ' ...';
            }
        } else if (\strlen($message) > $max_length) {
            $message = substr($message, 0, $max_length - 4) . ' ...';
        }
        return $message;
    }
}