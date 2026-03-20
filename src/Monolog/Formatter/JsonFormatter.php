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
use Stringable;
use Throwable;
/**
 * Encodes whatever record data is passed to it as json
 *
 * This can be useful to log to databases or remote APIs
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Json_Formatter extends Normalizer_Formatter
{
    public const BATCH_MODE_JSON = 1;
    public const BATCH_MODE_NEWLINES = 2;
    /**
     * @param self::BATCH_MODE_* $batchMode
     */
    public function __construct(protected int $batch_mode = self::BATCH_MODE_JSON, protected bool $append_newline = true, protected bool $ignore_empty_context_and_extra = false, protected bool $include_stacktraces = false)
    {
        parent::__construct();
    }
    /**
     * The batch mode option configures the formatting style for
     * multiple records. By default, multiple records will be
     * formatted as a JSON-encoded array. However, for
     * compatibility with some API endpoints, alternative styles
     * are available.
     */
    public function get_batch_mode(): int
    {
        return $this->batch_mode;
    }
    /**
     * True if newlines are appended to every formatted record
     */
    public function is_appending_newlines(): bool
    {
        return $this->append_newline;
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): string
    {
        $normalized = $this->normalize_record($record);
        return $this->to_json($normalized, true) . ($this->append_newline ? "\n" : '');
    }
    /**
     * @inheritDoc
     */
    public function format_batch(array $records): string
    {
        return match ($this->batch_mode) {
            static::BATCH_MODE_NEWLINES => $this->format_batch_newlines($records),
            default => $this->format_batch_json($records),
        };
    }
    /**
     * @return $this
     */
    public function include_stacktraces(bool $include = true): self
    {
        $this->include_stacktraces = $include;
        return $this;
    }
    /**
     * @return array<array<mixed>|bool|float|int|\stdClass|string|null>
     */
    protected function normalize_record(Log_Record $record): array
    {
        $normalized = parent::normalize_record($record);
        if (isset($normalized['context']) && $normalized['context'] === []) {
            if ($this->ignore_empty_context_and_extra) {
                unset($normalized['context']);
            } else {
                $normalized['context'] = new \stdClass();
            }
        }
        if (isset($normalized['extra']) && $normalized['extra'] === []) {
            if ($this->ignore_empty_context_and_extra) {
                unset($normalized['extra']);
            } else {
                $normalized['extra'] = new \stdClass();
            }
        }
        return $normalized;
    }
    /**
     * Return a JSON-encoded array of records.
     *
     * @phpstan-param LogRecord[] $records
     */
    protected function format_batch_json(array $records): string
    {
        $formatted = array_map($this->normalize_record(...), $records);
        return $this->to_json($formatted, true);
    }
    /**
     * Use new lines to separate records instead of a
     * JSON-encoded array.
     *
     * @phpstan-param LogRecord[] $records
     */
    protected function format_batch_newlines(array $records): string
    {
        $old_newline = $this->append_newline;
        $this->append_newline = false;
        $formatted = array_map($this->format(...), $records);
        $this->append_newline = $old_newline;
        return implode("\n", $formatted);
    }
    /**
     * Normalizes given $data.
     *
     * @return null|scalar|array<mixed[]|scalar|null|object>|object
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if ($depth > $this->max_normalize_depth) {
            return 'Over ' . $this->max_normalize_depth . ' levels deep, aborting normalization';
        }
        if (\is_array($data)) {
            $normalized = [];
            $count = 1;
            foreach ($data as $key => $value) {
                if ($count++ > $this->max_normalize_item_count) {
                    $normalized['...'] = 'Over ' . $this->max_normalize_item_count . ' items (' . \count($data) . ' total), aborting normalization';
                    break;
                }
                $normalized[$key] = $this->normalize($value, $depth + 1);
            }
            return $normalized;
        }
        if (\is_object($data)) {
            if ($data instanceof \DateTimeInterface) {
                return $this->format_date($data);
            }
            if ($data instanceof Throwable) {
                return $this->normalize_exception($data, $depth);
            }
            // if the object has specific json serializability we want to make sure we skip the __toString treatment below
            if ($data instanceof \JsonSerializable) {
                return $data;
            }
            if ($data instanceof Stringable) {
                try {
                    return $data->__toString();
                } catch (Throwable) {
                    return $data::class;
                }
            }
            if ($data::class === '__PHP_Incomplete_Class') {
                return new \ArrayObject($data);
            }
            return $data;
        }
        if (\is_resource($data)) {
            return parent::normalize($data);
        }
        return $data;
    }
    /**
     * Normalizes given exception with or without its own stack trace based on
     * `includeStacktraces` property.
     *
     * @return array<array-key, string|int|array<string|int|array<string>>>
     */
    protected function normalize_exception(Throwable $e, int $depth = 0): array
    {
        $data = parent::normalize_exception($e, $depth);
        if (!$this->include_stacktraces) {
            unset($data['trace']);
        }
        return $data;
    }
}