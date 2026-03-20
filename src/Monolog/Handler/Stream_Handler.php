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

use Monolog\Level;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Stores to any stream resource
 *
 * Can be used to store into php://stderr, remote and local files, etc.
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Stream_Handler extends Abstract_Processing_Handler
{
    protected const MAX_CHUNK_SIZE = 2147483647;
    /** 10MB */
    protected const DEFAULT_CHUNK_SIZE = 10 * 1024 * 1024;
    protected int $stream_chunk_size;
    /** @var resource|null */
    protected $stream;
    protected string|null $url = null;
    private string|null $error_message = null;
    /** @var true|null */
    private bool|null $dir_created = null;
    private bool $retrying = false;
    private int|null $inode_url = null;
    /**
     * @param resource|string $stream         If a missing path can't be created, an UnexpectedValueException will be thrown on first write
     * @param int|null        $filePermission Optional file permissions (default (0644) are only for owner read/write)
     * @param bool            $useLocking     Try to lock log file before doing any writes
     * @param string          $fileOpenMode   The fopen() mode used when opening a file, if $stream is a file path
     *
     * @throws \InvalidArgumentException If stream is not a resource or string
     */
    public function __construct($stream, int|string|Level $level = Level::Debug, bool $bubble = true, protected int|null $file_permission = null, protected bool $use_locking = false, protected string $file_open_mode = 'a')
    {
        parent::__construct($level, $bubble);
        if (($php_memory_limit = Utils::expand_ini_shorthand_bytes(\ini_get('memory_limit'))) !== false) {
            if ($php_memory_limit > 0) {
                // use max 10% of allowed memory for the chunk size, and at least 100KB
                $this->stream_chunk_size = min(static::MAX_CHUNK_SIZE, max((int) ($php_memory_limit / 10), 100 * 1024));
            } else {
                // memory is unlimited, set to the default 10MB
                $this->stream_chunk_size = static::DEFAULT_CHUNK_SIZE;
            }
        } else {
            // no memory limit information, set to the default 10MB
            $this->stream_chunk_size = static::DEFAULT_CHUNK_SIZE;
        }
        if (\is_resource($stream)) {
            $this->stream = $stream;
            stream_set_chunk_size($this->stream, $this->stream_chunk_size);
        } elseif (\is_string($stream)) {
            $this->url = Utils::canonicalize_path($stream);
        } else {
            throw new \InvalidArgumentException('A stream must either be a resource or a string.');
        }
    }
    /**
     * @inheritDoc
     */
    public function reset(): void
    {
        parent::reset();
        // auto-close on reset to make sure we periodically close the file in long running processes
        // as long as they correctly call reset() between jobs
        if ($this->url !== null && $this->url !== 'php://memory') {
            $this->close();
        }
    }
    /**
     * @inheritDoc
     */
    public function close(): void
    {
        if (null !== $this->url && \is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
        $this->dir_created = null;
    }
    /**
     * Return the currently active stream if it is open
     *
     * @return resource|null
     */
    public function get_stream()
    {
        return $this->stream;
    }
    /**
     * Return the stream URL if it was configured with a URL and not an active resource
     */
    public function get_url(): ?string
    {
        return $this->url;
    }
    public function get_stream_chunk_size(): int
    {
        return $this->stream_chunk_size;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        if ($this->has_url_inode_was_changed()) {
            $this->close();
            $this->write($record);
            return;
        }
        if (!\is_resource($this->stream)) {
            $url = $this->url;
            if (null === $url || '' === $url) {
                throw new \LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().' . Utils::get_record_message_for_exception($record));
            }
            $this->create_dir($url);
            $this->error_message = null;
            set_error_handler($this->custom_error_handler(...));
            try {
                $stream = fopen($url, $this->file_open_mode);
                if ($this->file_permission !== null) {
                    @chmod($url, $this->file_permission);
                }
            } finally {
                restore_error_handler();
            }
            if (!\is_resource($stream)) {
                $this->stream = null;
                throw new \UnexpectedValueException(sprintf('The stream or file "%s" could not be opened in append mode: ' . $this->error_message, $url) . Utils::get_record_message_for_exception($record));
            }
            stream_set_chunk_size($stream, $this->stream_chunk_size);
            $this->stream = $stream;
            $this->inode_url = $this->get_inode_from_url();
        }
        $stream = $this->stream;
        if ($this->use_locking) {
            // ignoring errors here, there's not much we can do about them
            flock($stream, LOCK_EX);
        }
        $this->error_message = null;
        set_error_handler($this->custom_error_handler(...));
        try {
            $this->stream_write($stream, $record);
        } finally {
            restore_error_handler();
        }
        if ($this->error_message !== null) {
            $error = $this->error_message;
            // close the resource if possible to reopen it, and retry the failed write
            if (!$this->retrying && $this->url !== null && $this->url !== 'php://memory') {
                $this->retrying = true;
                $this->close();
                $this->write($record);
                return;
            }
            throw new \UnexpectedValueException('Writing to the log file failed: ' . $error . Utils::get_record_message_for_exception($record));
        }
        $this->retrying = false;
        if ($this->use_locking) {
            flock($stream, LOCK_UN);
        }
    }
    /**
     * Write to stream
     * @param resource $stream
     */
    protected function stream_write($stream, Log_Record $record): void
    {
        fwrite($stream, (string) $record->formatted);
    }
    /**
     * @return true
     */
    private function custom_error_handler(int $code, string $msg): bool
    {
        $this->error_message = preg_replace('{^(fopen|mkdir|fwrite)\(.*?\): }', '', $msg);
        return true;
    }
    private function get_dir_from_stream(string $stream): ?string
    {
        $pos = strpos($stream, '://');
        if ($pos === false) {
            return \dirname($stream);
        }
        if (str_starts_with($stream, 'file://')) {
            return \dirname(substr($stream, 7));
        }
        return null;
    }
    private function create_dir(string $url): void
    {
        // Do not try to create dir if it has already been tried.
        if (true === $this->dir_created) {
            return;
        }
        $dir = $this->get_dir_from_stream($url);
        if (null !== $dir && !is_dir($dir)) {
            $this->error_message = null;
            set_error_handler(fn(...$args) => $this->custom_error_handler(...$args));
            $status = mkdir($dir, 0777, true);
            restore_error_handler();
            if (false === $status && !is_dir($dir) && !str_contains((string) $this->error_message, 'File exists')) {
                throw new \UnexpectedValueException(sprintf('There is no existing directory at "%s" and it could not be created: ' . $this->error_message, $dir));
            }
        }
        $this->dir_created = true;
    }
    private function get_inode_from_url(): ?int
    {
        if ($this->url === null || str_starts_with($this->url, 'php://')) {
            return null;
        }
        $inode = @fileinode($this->url);
        return $inode === false ? null : $inode;
    }
    private function has_url_inode_was_changed(): bool
    {
        if ($this->inode_url === null || $this->retrying || $this->inode_url === $this->get_inode_from_url()) {
            return false;
        }
        $this->retrying = true;
        return true;
    }
}