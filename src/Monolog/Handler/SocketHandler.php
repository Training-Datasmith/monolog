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
/**
 * Stores to any socket - uses fsockopen() or pfsockopen().
 *
 * @author Pablo de Leon Belloc <pablolb@gmail.com>
 * @see    http://php.net/manual/en/function.fsockopen.php
 */
class Socket_Handler extends Abstract_Processing_Handler
{
    private float $connection_timeout;
    /** @var resource|null */
    private $resource;
    private float $timeout;
    private float $writing_timeout;
    private int|null $last_sent_bytes = null;
    private int|null $errno = null;
    private string|null $errstr = null;
    private float|null $last_writing_at = null;
    /**
     * @param string     $connectionString  Socket connection string
     * @param bool       $persistent        Flag to enable/disable persistent connections
     * @param float      $timeout           Socket timeout to wait until the request is being aborted
     * @param float      $writingTimeout    Socket timeout to wait until the request should've been sent/written
     * @param float|null $connectionTimeout Socket connect timeout to wait until the connection should've been
     *                                      established
     * @param int|null   $chunkSize         Sets the chunk size. Only has effect during connection in the writing cycle
     *
     * @throws \InvalidArgumentException If an invalid timeout value (less than 0) is passed.
     */
    public function __construct(private readonly string $connection_string, int|string|\Monolog\Level $level = Level::Debug, bool $bubble = true, private bool $persistent = false, float $timeout = 0.0, float $writing_timeout = 10.0, ?float $connection_timeout = null, private int|null $chunk_size = null)
    {
        parent::__construct($level, $bubble);
        if ($connection_timeout !== null) {
            $this->validate_timeout($connection_timeout);
        }
        $this->connection_timeout = $connection_timeout ?? (float) \ini_get('default_socket_timeout');
        $this->validate_timeout($timeout);
        $this->timeout = $timeout;
        $this->validate_timeout($writing_timeout);
        $this->writing_timeout = $writing_timeout;
    }
    /**
     * Connect (if necessary) and write to the socket
     *
     * @inheritDoc
     *
     * @throws \UnexpectedValueException
     * @throws \RuntimeException
     */
    protected function write(Log_Record $record): void
    {
        $this->connect_if_not_connected();
        $data = $this->generate_data_stream($record);
        $this->write_to_socket($data);
    }
    /**
     * We will not close a PersistentSocket instance so it can be reused in other requests.
     */
    public function close(): void
    {
        if (!$this->is_persistent()) {
            $this->close_socket();
        }
    }
    /**
     * Close socket, if open
     */
    public function close_socket(): void
    {
        if (\is_resource($this->resource)) {
            fclose($this->resource);
            $this->resource = null;
        }
    }
    /**
     * Set socket connection to be persistent. It only has effect before the connection is initiated.
     *
     * @return $this
     */
    public function set_persistent(bool $persistent): self
    {
        $this->persistent = $persistent;
        return $this;
    }
    /**
     * Set connection timeout.  Only has effect before we connect.
     *
     * @see http://php.net/manual/en/function.fsockopen.php
     * @return $this
     */
    public function set_connection_timeout(float $seconds): self
    {
        $this->validate_timeout($seconds);
        $this->connection_timeout = $seconds;
        return $this;
    }
    /**
     * Set write timeout. Only has effect before we connect.
     *
     * @see http://php.net/manual/en/function.stream-set-timeout.php
     * @return $this
     */
    public function set_timeout(float $seconds): self
    {
        $this->validate_timeout($seconds);
        $this->timeout = $seconds;
        return $this;
    }
    /**
     * Set writing timeout. Only has effect during connection in the writing cycle.
     *
     * @param  float $seconds 0 for no timeout
     * @return $this
     */
    public function set_writing_timeout(float $seconds): self
    {
        $this->validate_timeout($seconds);
        $this->writing_timeout = $seconds;
        return $this;
    }
    /**
     * Set chunk size. Only has effect during connection in the writing cycle.
     *
     * @return $this
     */
    public function set_chunk_size(int $bytes): self
    {
        $this->chunk_size = $bytes;
        return $this;
    }
    /**
     * Get current connection string
     */
    public function get_connection_string(): string
    {
        return $this->connection_string;
    }
    /**
     * Get persistent setting
     */
    public function is_persistent(): bool
    {
        return $this->persistent;
    }
    /**
     * Get current connection timeout setting
     */
    public function get_connection_timeout(): float
    {
        return $this->connection_timeout;
    }
    /**
     * Get current in-transfer timeout
     */
    public function get_timeout(): float
    {
        return $this->timeout;
    }
    /**
     * Get current local writing timeout
     */
    public function get_writing_timeout(): float
    {
        return $this->writing_timeout;
    }
    /**
     * Get current chunk size
     */
    public function get_chunk_size(): ?int
    {
        return $this->chunk_size;
    }
    /**
     * Check to see if the socket is currently available.
     *
     * UDP might appear to be connected but might fail when writing.  See http://php.net/fsockopen for details.
     */
    public function is_connected(): bool
    {
        return \is_resource($this->resource) && !feof($this->resource);
        // on TCP - other party can close connection.
    }
    /**
     * Wrapper to allow mocking
     *
     * @return resource|false
     */
    protected function pfsockopen()
    {
        return @pfsockopen($this->connection_string, -1, $this->errno, $this->errstr, $this->connection_timeout);
    }
    /**
     * Wrapper to allow mocking
     *
     * @return resource|false
     */
    protected function fsockopen()
    {
        return @fsockopen($this->connection_string, -1, $this->errno, $this->errstr, $this->connection_timeout);
    }
    /**
     * Wrapper to allow mocking
     *
     * @see http://php.net/manual/en/function.stream-set-timeout.php
     */
    protected function stream_set_timeout(): bool
    {
        $seconds = floor($this->timeout);
        $microseconds = round(($this->timeout - $seconds) * 1000000.0);
        if (!\is_resource($this->resource)) {
            throw new \LogicException('streamSetTimeout called but $this->resource is not a resource');
        }
        return stream_set_timeout($this->resource, (int) $seconds, (int) $microseconds);
    }
    /**
     * Wrapper to allow mocking
     *
     * @see http://php.net/manual/en/function.stream-set-chunk-size.php
     *
     * @return int|false
     */
    protected function stream_set_chunk_size(): int|bool
    {
        if (!\is_resource($this->resource)) {
            throw new \LogicException('streamSetChunkSize called but $this->resource is not a resource');
        }
        if (null === $this->chunk_size) {
            throw new \LogicException('streamSetChunkSize called but $this->chunkSize is not set');
        }
        return stream_set_chunk_size($this->resource, $this->chunk_size);
    }
    /**
     * Wrapper to allow mocking
     *
     * @return int|false
     */
    protected function fwrite(string $data): int|bool
    {
        if (!\is_resource($this->resource)) {
            throw new \LogicException('fwrite called but $this->resource is not a resource');
        }
        return @fwrite($this->resource, $data);
    }
    /**
     * Wrapper to allow mocking
     *
     * @return mixed[]|bool
     */
    protected function stream_get_metadata(): array|bool
    {
        if (!\is_resource($this->resource)) {
            throw new \LogicException('streamGetMetadata called but $this->resource is not a resource');
        }
        return stream_get_meta_data($this->resource);
    }
    private function validate_timeout(float $value): void
    {
        if ($value < 0) {
            throw new \InvalidArgumentException("Timeout must be 0 or a positive float (got {$value})");
        }
    }
    private function connect_if_not_connected(): void
    {
        if ($this->is_connected()) {
            return;
        }
        $this->connect();
    }
    protected function generate_data_stream(Log_Record $record): string
    {
        return (string) $record->formatted;
    }
    /**
     * @return resource|null
     */
    protected function get_resource()
    {
        return $this->resource;
    }
    private function connect(): void
    {
        $this->create_socket_resource();
        $this->set_socket_timeout();
        $this->set_stream_chunk_size();
    }
    private function create_socket_resource(): void
    {
        if ($this->is_persistent()) {
            $resource = $this->pfsockopen();
        } else {
            $resource = $this->fsockopen();
        }
        if (\is_bool($resource)) {
            throw new \UnexpectedValueException("Failed connecting to {$this->connection_string} ({$this->errno}: {$this->errstr})");
        }
        $this->resource = $resource;
    }
    private function set_socket_timeout(): void
    {
        if (!$this->stream_set_timeout()) {
            throw new \UnexpectedValueException('Failed setting timeout with stream_set_timeout()');
        }
    }
    private function set_stream_chunk_size(): void
    {
        if (null !== $this->chunk_size && false === $this->stream_set_chunk_size()) {
            throw new \UnexpectedValueException('Failed setting chunk size with stream_set_chunk_size()');
        }
    }
    private function write_to_socket(string $data): void
    {
        $length = \strlen($data);
        $sent = 0;
        $this->last_sent_bytes = $sent;
        while ($this->is_connected() && $sent < $length) {
            if (0 === $sent) {
                $chunk = $this->fwrite($data);
            } else {
                $chunk = $this->fwrite(substr($data, $sent));
            }
            if ($chunk === false) {
                throw new \RuntimeException('Could not write to socket');
            }
            $sent += $chunk;
            $socket_info = $this->stream_get_metadata();
            if (\is_array($socket_info) && (bool) $socket_info['timed_out']) {
                throw new \RuntimeException('Write timed-out');
            }
            if ($this->writing_is_timed_out($sent)) {
                throw new \RuntimeException("Write timed-out, no data sent for `{$this->writing_timeout}` seconds, probably we got disconnected (sent {$sent} of {$length})");
            }
        }
        if (!$this->is_connected() && $sent < $length) {
            throw new \RuntimeException("End-of-file reached, probably we got disconnected (sent {$sent} of {$length})");
        }
    }
    private function writing_is_timed_out(int $sent): bool
    {
        // convert to ms
        if (0.0 === $this->writing_timeout) {
            return false;
        }
        if ($sent !== $this->last_sent_bytes) {
            $this->last_writing_at = microtime(true);
            $this->last_sent_bytes = $sent;
            return false;
        }
        usleep(100);
        if (microtime(true) - (float) $this->last_writing_at >= $this->writing_timeout) {
            $this->close_socket();
            return true;
        }
        return false;
    }
}