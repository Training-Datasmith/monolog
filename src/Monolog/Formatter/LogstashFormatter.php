<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Monolog\LogRecord;

/**
 * Serializes a log message to Logstash Event Format
 *
 * @see https://www.elastic.co/products/logstash
 * @see https://github.com/elastic/logstash/blob/master/logstash-core/src/main/java/org/logstash/Event.java
 *
 * @author Tim Mower <timothy.mower@gmail.com>
 */
class LogstashFormatter extends NormalizerFormatter
{
    /**
     * @var string the name of the system for the Logstash log message, used to fill the @source field
     */
    protected string $systemName;

    /**
     * @param string      $applicationName The application that sends the data, used as the "type" field of logstash
     * @param string|null $systemName      The system/machine name, used as the "source" field of logstash, defaults to the hostname of the machine
     * @param string      $extraKey        The key for extra keys inside logstash "fields", defaults to extra
     * @param string      $contextKey      The key for context keys inside logstash "fields", defaults to context
     */
    public function __construct(protected string $applicationName, ?string $systemName = null, protected string $extraKey = 'extra', protected string $contextKey = 'context')
    {
        // logstash requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');

        $this->systemName = $systemName ?? (string) gethostname();
    }

    /**
     * @inheritDoc
     */
    public function format(LogRecord $record): string
    {
        $recordData = parent::format($record);

        $message = [
            '@timestamp' => $recordData['datetime'],
            '@version' => 1,
            'host' => $this->systemName,
        ];
        if (isset($recordData['message'])) {
            $message['message'] = $recordData['message'];
        }
        if (isset($recordData['channel'])) {
            $message['type'] = $recordData['channel'];
            $message['channel'] = $recordData['channel'];
        }
        if (isset($recordData['level_name'])) {
            $message['level'] = $recordData['level_name'];
        }
        if (isset($recordData['level'])) {
            $message['monolog_level'] = $recordData['level'];
        }
        if ('' !== $this->applicationName) {
            $message['type'] = $this->applicationName;
        }
        if (\count($recordData['extra']) > 0) {
            $message[$this->extraKey] = $recordData['extra'];
        }
        if (\count($recordData['context']) > 0) {
            $message[$this->contextKey] = $recordData['context'];
        }

        return $this->toJson($message) . "\n";
    }
}
