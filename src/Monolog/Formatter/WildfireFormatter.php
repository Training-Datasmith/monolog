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
 * Serializes a log message according to Wildfire's header requirements
 *
 * @author Eric Clemmons (@ericclemmons) <eric@uxdriven.com>
 * @author Christophe Coevoet <stof@notk.org>
 * @author Kirill chEbba Chebunin <iam@chebba.org>
 */
class Wildfire_Formatter extends Normalizer_Formatter
{
    /**
     * @param string|null $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct(?string $date_format = null)
    {
        parent::__construct($date_format);
        // http headers do not like non-ISO-8559-1 characters
        $this->remove_json_encode_option(JSON_UNESCAPED_UNICODE);
    }
    /**
     * Translates Monolog log levels to Wildfire levels.
     *
     * @return 'LOG'|'INFO'|'WARN'|'ERROR'
     */
    private function to_wildfire_level(Level $level): string
    {
        return match ($level) {
            Level::Debug => 'LOG',
            Level::Info => 'INFO',
            Level::Notice => 'INFO',
            Level::Warning => 'WARN',
            Level::Error => 'ERROR',
            Level::Critical => 'ERROR',
            Level::Alert => 'ERROR',
            Level::Emergency => 'ERROR',
        };
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): string
    {
        // Retrieve the line and file if set and remove them from the formatted extra
        $file = $line = '';
        if (isset($record->extra['file'])) {
            $file = $record->extra['file'];
            unset($record->extra['file']);
        }
        if (isset($record->extra['line'])) {
            $line = $record->extra['line'];
            unset($record->extra['line']);
        }
        $message = ['message' => $record->message];
        $handle_error = false;
        if (\count($record->context) > 0) {
            $message['context'] = $this->normalize($record->context);
            $handle_error = true;
        }
        if (\count($record->extra) > 0) {
            $message['extra'] = $this->normalize($record->extra);
            $handle_error = true;
        }
        if (\count($message) === 1) {
            $message = reset($message);
        }
        if (is_array($message) && isset($message['context']) && \is_array($message['context']) && isset($message['context']['table'])) {
            $type = 'TABLE';
            $label = $record->channel . ': ' . $record->message;
            $message = $message['context']['table'];
        } else {
            $type = $this->to_wildfire_level($record->level);
            $label = $record->channel;
        }
        // Create JSON object describing the appearance of the message in the console
        $json = $this->to_json([['Type' => $type, 'File' => $file, 'Line' => $line, 'Label' => $label], $message], $handle_error);
        // The message itself is a serialization of the above JSON object + it's length
        return sprintf('%d|%s|', \strlen($json), $json);
    }
    /**
     * @inheritDoc
     *
     * @phpstan-return never
     */
    public function format_batch(array $records): never
    {
        throw new \BadMethodCallException('Batch formatting does not make sense for the WildfireFormatter');
    }
    /**
     * @inheritDoc
     *
     * @return null|scalar|array<mixed[]|scalar|null>|object
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if (\is_object($data) && !$data instanceof \DateTimeInterface) {
            return $data;
        }
        return parent::normalize($data, $depth);
    }
}