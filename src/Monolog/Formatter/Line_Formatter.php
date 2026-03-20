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

use Closure;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Formats incoming records into a one-line string
 *
 * This is especially useful for logging to files
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
class Line_Formatter extends Normalizer_Formatter
{
    public const SIMPLE_FORMAT = "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n";
    protected string $format;
    protected bool $include_stacktraces;
    protected ?int $max_level_name_length = null;
    protected string $indent_stacktraces = '';
    protected Closure|null $stacktraces_parser = null;
    protected string $base_path = '';
    /**
     * @param string|null $format                The format of the message
     * @param string|null $dateFormat            The format of the timestamp: one supported by DateTime::format
     * @param bool        $allowInlineLineBreaks Whether to allow inline line breaks in log entries
     */
    public function __construct(?string $format = null, ?string $date_format = null, protected bool $allow_inline_line_breaks = false, protected bool $ignore_empty_context_and_extra = false, bool $include_stacktraces = false)
    {
        $this->format = $format ?? static::SIMPLE_FORMAT;
        $this->include_stacktraces($include_stacktraces);
        parent::__construct($date_format);
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
     * @return $this
     */
    public function include_stacktraces(bool $include = true, ?Closure $parser = null): self
    {
        $this->include_stacktraces = $include;
        if ($this->include_stacktraces) {
            $this->allow_inline_line_breaks = true;
            $this->stacktraces_parser = $parser;
        }
        return $this;
    }
    /**
     * Indent stack traces to separate them a bit from the main log record messages
     *
     * @param  string $indent The string used to indent, for example "    "
     * @return $this
     */
    public function indent_stacktraces(string $indent): self
    {
        $this->indent_stacktraces = $indent;
        return $this;
    }
    /**
     * @return $this
     */
    public function allow_inline_line_breaks(bool $allow = true): self
    {
        $this->allow_inline_line_breaks = $allow;
        return $this;
    }
    /**
     * @return $this
     */
    public function ignore_empty_context_and_extra(bool $ignore = true): self
    {
        $this->ignore_empty_context_and_extra = $ignore;
        return $this;
    }
    /**
     * Allows cutting the level name to get fixed-length levels like INF for INFO, ERR for ERROR if you set this to 3 for example
     *
     * @param  int|null $maxLevelNameLength Maximum characters for the level name. Set null for infinite length (default)
     * @return $this
     */
    public function set_max_level_name_length(?int $max_level_name_length = null): self
    {
        $this->max_level_name_length = $max_level_name_length;
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function format(Log_Record $record): string
    {
        $vars = parent::format($record);
        if ($this->max_level_name_length !== null) {
            $vars['level_name'] = substr((string) $vars['level_name'], 0, $this->max_level_name_length);
        }
        $output = $this->format;
        foreach ($vars['extra'] as $var => $val) {
            if (str_contains($output, '%extra.' . $var . '%')) {
                $output = str_replace('%extra.' . $var . '%', $this->stringify($val), $output);
                unset($vars['extra'][$var]);
            }
        }
        foreach ($vars['context'] as $var => $val) {
            if (str_contains($output, '%context.' . $var . '%')) {
                $output = str_replace('%context.' . $var . '%', $this->stringify($val), $output);
                unset($vars['context'][$var]);
            }
        }
        if ($this->ignore_empty_context_and_extra) {
            if (\count($vars['context']) === 0) {
                unset($vars['context']);
                $output = str_replace('%context%', '', $output);
            }
            if (\count($vars['extra']) === 0) {
                unset($vars['extra']);
                $output = str_replace('%extra%', '', $output);
            }
        }
        foreach ($vars as $var => $val) {
            if (str_contains($output, '%' . $var . '%')) {
                $output = str_replace('%' . $var . '%', $this->stringify($val), $output);
            }
        }
        // remove leftover %extra.xxx% and %context.xxx% if any
        if (str_contains($output, '%')) {
            $output = preg_replace('/%(?:extra|context)\..+?%/', '', $output);
            if (null === $output) {
                $pcre_error_code = preg_last_error();
                throw new \RuntimeException('Failed to run preg_replace: ' . $pcre_error_code . ' / ' . preg_last_error_msg());
            }
        }
        return $output;
    }
    public function format_batch(array $records): string
    {
        $message = '';
        foreach ($records as $record) {
            $message .= $this->format($record);
        }
        return $message;
    }
    /**
     * @param mixed $value
     */
    public function stringify($value): string
    {
        return $this->replace_newlines($this->convert_to_string($value));
    }
    protected function normalize_exception(\Throwable $e, int $depth = 0): string
    {
        $str = $this->format_exception($e);
        $previous = $e->get_previous();
        while ($previous instanceof \Throwable) {
            $depth++;
            if ($depth > $this->max_normalize_depth) {
                $str .= "\n[previous exception] Over " . $this->max_normalize_depth . ' levels deep, aborting normalization';
                break;
            }
            $str .= "\n[previous exception] " . $this->format_exception($previous);
            $previous = $previous->get_previous();
        }
        return $str;
    }
    /**
     * @param mixed $data
     */
    protected function convert_to_string($data): string
    {
        if (null === $data || \is_bool($data)) {
            return var_export($data, true);
        }
        if (\is_scalar($data)) {
            return (string) $data;
        }
        return $this->to_json($data, true);
    }
    protected function replace_newlines(string $str): string
    {
        if ($this->allow_inline_line_breaks) {
            if (str_starts_with($str, '{') || str_starts_with($str, '[')) {
                $str = preg_replace('/(?<!\\\\)\\\\[rn]/', "\n", $str);
                if (null === $str) {
                    $pcre_error_code = preg_last_error();
                    throw new \RuntimeException('Failed to run preg_replace: ' . $pcre_error_code . ' / ' . preg_last_error_msg());
                }
            }
            return $str;
        }
        return str_replace(["\r\n", "\r", "\n"], ' ', $str);
    }
    private function format_exception(\Throwable $e): string
    {
        $str = '[object] (' . Utils::get_class($e) . '(code: ' . $e->get_code();
        if ($e instanceof \Soap_Fault) {
            if (isset($e->faultcode)) {
                $str .= ' faultcode: ' . $e->faultcode;
            }
            if (isset($e->faultactor)) {
                $str .= ' faultactor: ' . $e->faultactor;
            }
            if (isset($e->detail)) {
                if (\is_string($e->detail)) {
                    $str .= ' detail: ' . $e->detail;
                } elseif (\is_object($e->detail) || \is_array($e->detail)) {
                    $str .= ' detail: ' . $this->to_json($e->detail, true);
                }
            }
        }
        $file = $e->get_file();
        if ($this->base_path !== '') {
            $file = preg_replace('{^' . preg_quote($this->base_path) . '}', '', $file);
        }
        $str .= '): ' . $e->get_message() . ' at ' . strtr((string) $file, DIRECTORY_SEPARATOR, '/') . ':' . $e->get_line() . ')';
        if ($this->include_stacktraces) {
            $str .= $this->stacktraces_parser($e);
        }
        return $str;
    }
    private function stacktraces_parser(\Throwable $e): string
    {
        $trace = $e->get_trace_as_string();
        if ($this->base_path !== '') {
            $trace = preg_replace('{^(#\d+ )' . preg_quote($this->base_path) . '}m', '$1', $trace) ?? $trace;
        }
        if ($this->stacktraces_parser !== null) {
            $trace = $this->stacktraces_parser_custom($trace);
        }
        if ($this->indent_stacktraces !== '') {
            $trace = str_replace("\n", "\n{$this->indent_stacktraces}", $trace);
        }
        if (trim($trace) === '') {
            return '';
        }
        return "\n{$this->indent_stacktraces}[stacktrace]\n{$this->indent_stacktraces}" . strtr($trace, DIRECTORY_SEPARATOR, '/') . "\n";
    }
    private function stacktraces_parser_custom(string $trace): string
    {
        return implode("\n", array_filter(array_map($this->stacktraces_parser, explode("\n", $trace)), fn($line): bool => is_string($line) && trim($line) !== ''));
    }
}