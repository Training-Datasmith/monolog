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

use Monolog\Json_Serializable_Date_Time_Immutable;
use Monolog\Log_Record;
use Monolog\Utils;
use Throwable;
/**
 * Normalizes incoming records to remove objects/resources so it's easier to dump to various targets
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Normalizer_Formatter implements Formatter_Interface
{
    public const SIMPLE_DATE = "Y-m-d\\TH:i:sP";
    protected string $date_format;
    protected int $max_normalize_depth = 9;
    protected int $max_normalize_item_count = 1000;
    protected ?int $max_trace_length = null;
    private int $json_encode_options = Utils::DEFAULT_JSON_FLAGS;
    protected string $base_path = '';
    /**
     * @param string|null $dateFormat The format of the timestamp: one supported by DateTime::format
     */
    public function __construct(?string $date_format = null)
    {
        $this->date_format = $date_format ?? static::SIMPLE_DATE;
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): array
    {
        return $this->normalize_record($record);
    }
    /**
     * Normalize an arbitrary value to a scalar|array|null
     *
     * @return null|scalar|array<mixed[]|scalar|null>
     */
    public function normalize_value(mixed $data): mixed
    {
        return $this->normalize($data);
    }
    /**
     * @inheritDoc
     */
    public function format_batch(array $records): array
    {
        foreach ($records as $key => $record) {
            $records[$key] = $this->format($record);
        }
        return $records;
    }
    public function get_date_format(): string
    {
        return $this->date_format;
    }
    /**
     * @return $this
     */
    public function set_date_format(string $date_format): self
    {
        $this->date_format = $date_format;
        return $this;
    }
    /**
     * The maximum number of normalization levels to go through
     */
    public function get_max_normalize_depth(): int
    {
        return $this->max_normalize_depth;
    }
    /**
     * @return $this
     */
    public function set_max_normalize_depth(int $max_normalize_depth): self
    {
        $this->max_normalize_depth = $max_normalize_depth;
        return $this;
    }
    /**
     * The maximum number of items to normalize per level
     */
    public function get_max_normalize_item_count(): int
    {
        return $this->max_normalize_item_count;
    }
    /**
     * @return $this
     */
    public function set_max_normalize_item_count(int $max_normalize_item_count): self
    {
        $this->max_normalize_item_count = $max_normalize_item_count;
        return $this;
    }
    /**
     * The maximum number of stack trace frames to include
     */
    public function get_max_trace_length(): ?int
    {
        return $this->max_trace_length;
    }
    /**
     * @return $this
     */
    public function set_max_trace_length(?int $max_trace_length): self
    {
        $this->max_trace_length = $max_trace_length;
        return $this;
    }
    /**
     * Enables `json_encode` pretty print.
     *
     * @return $this
     */
    public function set_json_pretty_print(bool $enable): self
    {
        if ($enable) {
            $this->json_encode_options |= JSON_PRETTY_PRINT;
        } else {
            $this->json_encode_options &= ~JSON_PRETTY_PRINT;
        }
        return $this;
    }
    /**
     * Setting a base path will hide the base path from exception and stack trace file names to shorten them
     * @return $this
     */
    public function set_base_path(string $path = ''): self
    {
        if ($path !== '') {
            $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }
        $this->base_path = $path;
        return $this;
    }
    /**
     * Provided as extension point
     *
     * Because normalize is called with sub-values of context data etc, normalizeRecord can be
     * extended when data needs to be appended on the record array but not to other normalized data.
     *
     * @return array<mixed[]|scalar|null>
     */
    protected function normalize_record(Log_Record $record): array
    {
        /** @var array<mixed[]|scalar|null> $normalized */
        $normalized = $this->normalize($record->to_array());
        return $normalized;
    }
    /**
     * @return null|scalar|array<mixed[]|scalar|null>
     */
    protected function normalize(mixed $data, int $depth = 0): mixed
    {
        if ($depth > $this->max_normalize_depth) {
            return 'Over ' . $this->max_normalize_depth . ' levels deep, aborting normalization';
        }
        if (null === $data || \is_scalar($data)) {
            if (\is_float($data)) {
                if (is_infinite($data)) {
                    return ($data > 0 ? '' : '-') . 'INF';
                }
                if (is_nan($data)) {
                    return 'NaN';
                }
            }
            return $data;
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
        if ($data instanceof \DateTimeInterface) {
            return $this->format_date($data);
        }
        if (\is_object($data)) {
            if ($data instanceof Throwable) {
                return $this->normalize_exception($data, $depth);
            }
            if ($data instanceof \JsonSerializable) {
                /** @var null|scalar|array<mixed[]|scalar|null> $value */
                $value = $data->jsonSerialize();
            } elseif ($data::class === '__PHP_Incomplete_Class') {
                $accessor = new \ArrayObject($data);
                $value = (string) $accessor['__PHP_Incomplete_Class_Name'];
            } elseif (method_exists($data, '__toString')) {
                try {
                    /** @var string $value */
                    $value = $data->__toString();
                } catch (\Throwable) {
                    // if the toString method is failing, use the default behavior
                    /** @var null|scalar|array<mixed[]|scalar|null> $value */
                    $value = json_decode($this->to_json($data, true), true);
                }
            } else {
                // the rest is normalized by json encoding and decoding it
                /** @var null|scalar|array<mixed[]|scalar|null> $value */
                $value = json_decode($this->to_json($data, true), true);
            }
            return [Utils::get_class($data) => $value];
        }
        if (\is_resource($data)) {
            return sprintf('[resource(%s)]', get_resource_type($data));
        }
        return '[unknown(' . \gettype($data) . ')]';
    }
    /**
     * @return array<array-key, string|int|array<string|int|array<string>>>
     */
    protected function normalize_exception(Throwable $e, int $depth = 0): array
    {
        if ($depth > $this->max_normalize_depth) {
            return ['Over ' . $this->max_normalize_depth . ' levels deep, aborting normalization'];
        }
        if ($e instanceof \JsonSerializable) {
            return (array) $e->jsonSerialize();
        }
        $file = $e->get_file();
        if ($this->base_path !== '') {
            $file = preg_replace('{^' . preg_quote($this->base_path) . '}', '', $file);
        }
        $data = ['class' => Utils::get_class($e), 'message' => $e->get_message(), 'code' => (int) $e->get_code(), 'file' => $file . ':' . $e->get_line()];
        if ($e instanceof \Soap_Fault) {
            if (isset($e->faultcode)) {
                $data['faultcode'] = $e->faultcode;
            }
            if (isset($e->faultactor)) {
                $data['faultactor'] = $e->faultactor;
            }
            if (isset($e->detail)) {
                if (\is_string($e->detail)) {
                    $data['detail'] = $e->detail;
                } elseif (\is_object($e->detail) || \is_array($e->detail)) {
                    $data['detail'] = $this->to_json($e->detail, true);
                }
            }
        }
        $trace = array_slice($e->get_trace(), 0, $this->max_trace_length);
        foreach ($trace as $frame) {
            if (isset($frame['file'], $frame['line'])) {
                $file = $frame['file'];
                if ($this->base_path !== '') {
                    $file = preg_replace('{^' . preg_quote($this->base_path) . '}', '', $file);
                }
                $data['trace'][] = $file . ':' . $frame['line'];
            }
        }
        if (($previous = $e->get_previous()) instanceof \Throwable) {
            $data['previous'] = $this->normalize_exception($previous, $depth + 1);
        }
        return $data;
    }
    /**
     * Return the JSON representation of a value
     *
     * @param  mixed             $data
     * @throws \RuntimeException if encoding fails and errors are not ignored
     * @return string            if encoding fails and ignoreErrors is true 'null' is returned
     */
    protected function to_json($data, bool $ignore_errors = false): string
    {
        return Utils::json_encode($data, $this->json_encode_options, $ignore_errors);
    }
    protected function format_date(\DateTimeInterface $date): string
    {
        // in case the date format isn't custom then we defer to the custom JsonSerializableDateTimeImmutable
        // formatting logic, which will pick the right format based on whether useMicroseconds is on
        if ($this->date_format === self::SIMPLE_DATE && $date instanceof Json_Serializable_Date_Time_Immutable) {
            return (string) $date;
        }
        return $date->format($this->date_format);
    }
    /**
     * @return $this
     */
    public function add_json_encode_option(int $option): self
    {
        $this->json_encode_options |= $option;
        return $this;
    }
    /**
     * @return $this
     */
    public function remove_json_encode_option(int $option): self
    {
        $this->json_encode_options &= ~$option;
        return $this;
    }
}