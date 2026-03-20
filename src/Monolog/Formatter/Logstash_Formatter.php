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
 * Serializes a log message to Logstash Event Format
 *
 * @see https://www.elastic.co/products/logstash
 * @see https://github.com/elastic/logstash/blob/master/logstash-core/src/main/java/org/logstash/Event.java
 *
 * @author Tim Mower <timothy.mower@gmail.com>
 */
class Logstash_Formatter extends Normalizer_Formatter
{
    /**
     * @var string the name of the system for the Logstash log message, used to fill the @source field
     */
    protected string $system_name;
    /**
     * @param string      $applicationName The application that sends the data, used as the "type" field of logstash
     * @param string|null $systemName      The system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     * @param string      $extraKey        The key for extra keys inside logstash "fields", defaults to extra
     * @param string      $contextKey      The key for context keys inside logstash "fields", defaults to context
     */
    public function __construct(protected string $application_name, ?string $system_name = null, protected string $extra_key = 'extra', protected string $context_key = 'context')
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');
        $this->system_name = $system_name ?? (string) gethostname();
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): string
    {
        $record_data = parent::format($record);
        $message = ['@timestamp' => $record_data['datetime'], '@version' => 1, 'host' => $this->system_name];
        if (isset($record_data['message'])) {
            $message['message'] = $record_data['message'];
        }
        if (isset($record_data['channel'])) {
            $message['type'] = $record_data['channel'];
            $message['channel'] = $record_data['channel'];
        }
        if (isset($record_data['level_name'])) {
            $message['level'] = $record_data['level_name'];
        }
        if (isset($record_data['level'])) {
            $message['monolog_level'] = $record_data['level'];
        }
        if ('' !== $this->application_name) {
            $message['type'] = $this->application_name;
        }
        if (\count($record_data['extra']) > 0) {
            $message[$this->extra_key] = $record_data['extra'];
        }
        if (\count($record_data['context']) > 0) {
            $message[$this->context_key] = $record_data['context'];
        }
        return $this->to_json($message) . "\n";
    }
}