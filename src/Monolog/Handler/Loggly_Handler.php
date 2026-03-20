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

use Curl_Handle;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Loggly_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Sends errors to Loggly.
 *
 * @author Przemek Sobstel <przemek@sobstel.org>
 * @author Adam Pancutt <adam@pancutt.com>
 * @author Gregory Barchard <gregory@barchard.net>
 */
class Loggly_Handler extends Abstract_Processing_Handler
{
    protected const HOST = 'logs-01.loggly.com';
    protected const ENDPOINT_SINGLE = 'inputs';
    protected const ENDPOINT_BATCH = 'bulk';
    /**
     * Caches the curl handlers for every given endpoint.
     *
     * @var CurlHandle[]
     */
    protected array $curl_handlers = [];
    /** @var string[] */
    protected array $tag = [];
    /**
     * @param string $token API token supplied by Loggly
     *
     * @throws MissingExtensionException If the curl extension is missing
     */
    public function __construct(protected string $token, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if (!\extension_loaded('curl')) {
            throw new Missing_Extension_Exception('The curl extension is needed to use the LogglyHandler');
        }
        parent::__construct($level, $bubble);
    }
    /**
     * Loads and returns the shared curl handler for the given endpoint.
     */
    protected function get_curl_handler(string $endpoint): Curl_Handle
    {
        if (!\array_key_exists($endpoint, $this->curl_handlers)) {
            $this->curl_handlers[$endpoint] = $this->load_curl_handle($endpoint);
        }
        return $this->curl_handlers[$endpoint];
    }
    /**
     * Starts a fresh curl session for the given endpoint and returns its handler.
     */
    private function load_curl_handle(string $endpoint): Curl_Handle
    {
        $url = sprintf('https://%s/%s/%s/', static::HOST, $endpoint, $this->token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        return $ch;
    }
    /**
     * @param  string[]|string $tag
     * @return $this
     */
    public function set_tag(string|array $tag): self
    {
        if ('' === $tag || [] === $tag) {
            $this->tag = [];
        } else {
            $this->tag = \is_array($tag) ? $tag : [$tag];
        }
        return $this;
    }
    /**
     * @param  string[]|string $tag
     * @return $this
     */
    public function add_tag(string|array $tag): self
    {
        if ('' !== $tag) {
            $tag = \is_array($tag) ? $tag : [$tag];
            $this->tag = array_unique(array_merge($this->tag, $tag));
        }
        return $this;
    }
    protected function write(Log_Record $record): void
    {
        $this->send($record->formatted, static::ENDPOINT_SINGLE);
    }
    public function handle_batch(array $records): void
    {
        $level = $this->level;
        $records = array_filter($records, fn(\Monolog\Log_Record $record) => $record->level->value >= $level->value);
        if (\count($records) > 0) {
            $this->send($this->get_formatter()->format_batch($records), static::ENDPOINT_BATCH);
        }
    }
    protected function send(string $data, string $endpoint): void
    {
        $ch = $this->get_curl_handler($endpoint);
        $headers = ['Content-Type: application/json'];
        if (\count($this->tag) > 0) {
            $headers[] = 'X-LOGGLY-TAG: ' . implode(',', $this->tag);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        Curl\Util::execute($ch, 5);
    }
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Loggly_Formatter();
    }
}