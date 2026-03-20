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

use DateTimeZone;
use InvalidArgumentException;
use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Stores logs to files that are rotated every day and a limited number of files are kept.
 *
 * This rotation is only intended to be used as a workaround. Using logrotate to
 * handle the rotation is strongly encouraged when you can use it.
 *
 * @author Christophe Coevoet <stof@notk.org>
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Rotating_File_Handler extends Stream_Handler
{
    public const FILE_PER_DAY = 'Y-m-d';
    public const FILE_PER_MONTH = 'Y-m';
    public const FILE_PER_YEAR = 'Y';
    protected string $filename;
    protected bool|null $must_rotate = null;
    protected \DateTimeImmutable $next_rotation;
    protected string $filename_format;
    protected string $date_format;
    /**
     * @param int      $maxFiles       The maximal amount of files to keep (0 means unlimited)
     * @param int|null $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool     $useLocking     Try to lock log file before doing any writes
     */
    public function __construct(string $filename, protected int $max_files = 0, int|string|Level $level = Level::Debug, bool $bubble = true, ?int $file_permission = null, bool $use_locking = false, string $date_format = self::FILE_PER_DAY, string $filename_format = '{filename}-{date}', protected DateTimeZone|null $timezone = null)
    {
        $this->filename = Utils::canonicalize_path($filename);
        $this->set_filename_format($filename_format, $date_format);
        $this->next_rotation = $this->get_next_rotation();
        parent::__construct($this->get_timed_filename(), $level, $bubble, $file_permission, $use_locking);
    }
    /**
     * @inheritDoc
     */
    public function close(): void
    {
        parent::close();
        if (true === $this->must_rotate) {
            $this->rotate();
        }
    }
    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        parent::reset();
    }
    /**
     * @return $this
     */
    public function set_filename_format(string $filename_format, string $date_format): self
    {
        $this->set_date_format($date_format);
        if (substr_count($filename_format, '{date}') === 0) {
            throw new InvalidArgumentException('Invalid filename format - format must contain at least `{date}`, because otherwise rotating is impossible.');
        }
        $this->filename_format = $filename_format;
        $this->url = $this->get_timed_filename();
        $this->close();
        return $this;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        // on the first record written, if the log is new, we rotate (once per day) after the log has been written so that the new file exists
        if (null === $this->must_rotate) {
            $this->must_rotate = null === $this->url || !file_exists($this->url);
        }
        // if the next rotation is expired, then we rotate immediately
        if ($this->next_rotation <= $record->datetime) {
            $this->must_rotate = true;
            $this->close();
            // triggers rotation
        }
        parent::write($record);
        if (true === $this->must_rotate) {
            $this->close();
            // triggers rotation
        }
    }
    /**
     * Rotates the files.
     */
    protected function rotate(): void
    {
        // update filename
        $this->url = $this->get_timed_filename();
        $this->next_rotation = $this->get_next_rotation();
        $this->must_rotate = false;
        // skip GC of old logs if files are unlimited
        if (0 === $this->max_files) {
            return;
        }
        $log_files = glob($this->get_glob_pattern());
        if (false === $log_files) {
            // failed to glob
            return;
        }
        if ($this->max_files >= \count($log_files)) {
            // no files to remove
            return;
        }
        // Sorting the files by name to remove the older ones
        usort($log_files, fn($a, $b) => strcmp((string) $b, (string) $a));
        $base_path = dirname($this->filename);
        foreach (\array_slice($log_files, $this->max_files) as $file) {
            if (is_writable($file)) {
                // suppress errors here as unlink() might fail if two processes
                // are cleaning up/rotating at the same time
                set_error_handler(fn(int $errno, string $errstr, string $errfile, int $errline): bool => true);
                unlink($file);
                $dir = dirname($file);
                while ($dir !== $base_path) {
                    $entries = scandir($dir);
                    if ($entries === false || \count(array_diff($entries, ['.', '..'])) > 0) {
                        break;
                    }
                    rmdir($dir);
                    $dir = dirname($dir);
                }
                restore_error_handler();
            }
        }
    }
    protected function get_timed_filename(): string
    {
        $file_info = pathinfo($this->filename);
        $timed_filename = str_replace(['{filename}', '{date}'], [$file_info['filename'], (new \DateTimeImmutable(timezone: $this->timezone))->format($this->date_format)], ($file_info['dirname'] ?? '') . '/' . $this->filename_format);
        if (isset($file_info['extension'])) {
            $timed_filename .= '.' . $file_info['extension'];
        }
        return $timed_filename;
    }
    protected function get_glob_pattern(): string
    {
        $file_info = pathinfo($this->filename);
        $glob = str_replace(['{filename}', '{date}'], [$file_info['filename'], str_replace(['Y', 'y', 'm', 'd'], ['[0-9][0-9][0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]', '[0-9][0-9]'], $this->date_format)], ($file_info['dirname'] ?? '') . '/' . $this->filename_format);
        if (isset($file_info['extension'])) {
            $glob .= '.' . $file_info['extension'];
        }
        return $glob;
    }
    protected function set_date_format(string $date_format): void
    {
        if (0 === preg_match('{^[Yy](([/_.-]?m)([/_.-]?d)?)?$}', $date_format)) {
            throw new InvalidArgumentException('Invalid date format - format must be one of ' . 'RotatingFileHandler::FILE_PER_DAY ("Y-m-d"), RotatingFileHandler::FILE_PER_MONTH ("Y-m") ' . 'or RotatingFileHandler::FILE_PER_YEAR ("Y"), or you can set one of the ' . 'date formats using slashes, underscores and/or dots instead of dashes.');
        }
        $this->date_format = $date_format;
    }
    protected function get_next_rotation(): \DateTimeImmutable
    {
        return match (str_replace(['/', '_', '.'], '-', $this->date_format)) {
            self::FILE_PER_MONTH => (new \DateTimeImmutable('first day of next month'))->set_time(0, 0, 0),
            self::FILE_PER_YEAR => (new \DateTimeImmutable('first day of January next year'))->set_time(0, 0, 0),
            default => (new \DateTimeImmutable('tomorrow'))->set_time(0, 0, 0),
        };
    }
}