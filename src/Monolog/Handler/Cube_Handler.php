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
 * Logs to Cube.
 *
 * @link https://github.com/square/cube/wiki
 * @author Wan Chen <kami@kamisama.me>
 * @deprecated Since 2.8.0 and 3.2.0, Cube appears abandoned and thus we will drop this handler in Monolog 4
 */
class Cube_Handler extends Abstract_Processing_Handler
{
    private ?\Socket $udp_connection = null;
    private ?\Curl_Handle $http_connection = null;
    private readonly string $scheme;
    private readonly string $host;
    private readonly int $port;
    /** @var string[] */
    private array $accepted_schemes = ['http', 'udp'];
    /**
     * Create a Cube handler
     *
     * @throws \UnexpectedValueException when given url is not a valid url.
     *                                   A valid url must consist of three parts : protocol://host:port
     *                                   Only valid protocols used by Cube are http and udp
     */
    public function __construct(string $url, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        $url_info = parse_url($url);
        if ($url_info === false || !isset($url_info['scheme'], $url_info['host'], $url_info['port'])) {
            throw new \UnexpectedValueException('URL "' . $url . '" is not valid');
        }
        if (!\in_array($url_info['scheme'], $this->accepted_schemes, true)) {
            throw new \UnexpectedValueException('Invalid protocol (' . $url_info['scheme'] . ').' . ' Valid options are ' . implode(', ', $this->accepted_schemes));
        }
        $this->scheme = $url_info['scheme'];
        $this->host = $url_info['host'];
        $this->port = $url_info['port'];
        parent::__construct($level, $bubble);
    }
    /**
     * Establish a connection to an UDP socket
     *
     * @throws \LogicException           when unable to connect to the socket
     * @throws MissingExtensionException when there is no socket extension
     */
    protected function connect_udp(): void
    {
        if (!\extension_loaded('sockets')) {
            throw new Missing_Extension_Exception('The sockets extension is required to use udp URLs with the CubeHandler');
        }
        $udp_connection = socket_create(AF_INET, SOCK_DGRAM, 0);
        if (false === $udp_connection) {
            throw new \LogicException('Unable to create a socket');
        }
        $this->udp_connection = $udp_connection;
        if (!socket_connect($this->udp_connection, $this->host, $this->port)) {
            throw new \LogicException('Unable to connect to the socket at ' . $this->host . ':' . $this->port);
        }
    }
    /**
     * Establish a connection to an http server
     *
     * @throws \LogicException           when unable to connect to the socket
     * @throws MissingExtensionException when no curl extension
     */
    protected function connect_http(): void
    {
        if (!\extension_loaded('curl')) {
            throw new Missing_Extension_Exception('The curl extension is required to use http URLs with the CubeHandler');
        }
        $http_connection = curl_init('http://' . $this->host . ':' . $this->port . '/1.0/event/put');
        if (false === $http_connection) {
            throw new \LogicException('Unable to connect to ' . $this->host . ':' . $this->port);
        }
        $this->http_connection = $http_connection;
        curl_setopt($this->http_connection, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($this->http_connection, CURLOPT_RETURNTRANSFER, true);
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $date = $record->datetime;
        $data = ['time' => $date->format('Y-m-d\TH:i:s.uO')];
        $context = $record->context;
        if (isset($context['type'])) {
            $data['type'] = $context['type'];
            unset($context['type']);
        } else {
            $data['type'] = $record->channel;
        }
        $data['data'] = $context;
        $data['data']['level'] = $record->level;
        if ($this->scheme === 'http') {
            $this->write_http(Utils::json_encode($data));
        } else {
            $this->write_udp(Utils::json_encode($data));
        }
    }
    private function write_udp(string $data): void
    {
        if (null === $this->udp_connection) {
            $this->connect_udp();
        }
        if (null === $this->udp_connection) {
            throw new \LogicException('No UDP socket could be opened');
        }
        socket_send($this->udp_connection, $data, \strlen($data), 0);
    }
    private function write_http(string $data): void
    {
        if (null === $this->http_connection) {
            $this->connect_http();
        }
        if (null === $this->http_connection) {
            throw new \LogicException('No connection could be established');
        }
        curl_setopt($this->http_connection, CURLOPT_POSTFIELDS, '[' . $data . ']');
        curl_setopt($this->http_connection, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Content-Length: ' . \strlen('[' . $data . ']')]);
        Curl\Util::execute($this->http_connection, 5);
    }
}