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

use Elastic\Elasticsearch\Client as Client8;
use Elastic\Elasticsearch\Exception\InvalidArgumentException as ElasticInvalidArgumentException;
use Elastic\Elasticsearch\Response\Elasticsearch;
use Elasticsearch\Client;
use Elasticsearch\Common\Exceptions\RuntimeException as ElasticsearchRuntimeException;
use InvalidArgumentException;
use Monolog\Formatter\Elasticsearch_Formatter;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Level;
use Monolog\Log_Record;
use RuntimeException;
use Throwable;
/**
 * Elasticsearch handler
 *
 * @link https://www.elastic.co/guide/en/elasticsearch/client/php-api/current/index.html
 *
 * Simple usage example:
 *
 *    $client = \Elasticsearch\ClientBuilder::create()
 *        ->setHosts($hosts)
 *        ->build();
 *
 *    $options = array(
 *        'index' => 'elastic_index_name',
 *        'type'  => 'elastic_doc_type',
 *    );
 *    $handler = new ElasticsearchHandler($client, $options);
 *    $log = new Logger('application');
 *    $log->pushHandler($handler);
 *
 * @author Avtandil Kikabidze <akalongman@gmail.com>
 * @phpstan-type Options array{
 *     index: string,
 *     type: string,
 *     ignore_error: bool,
 *     op_type: 'index'|'create'
 * }
 * @phpstan-type InputOptions array{
 *     index?: string,
 *     type?: string,
 *     ignore_error?: bool,
 *     op_type?: 'index'|'create'
 * }
 */
class Elasticsearch_Handler extends Abstract_Processing_Handler
{
    /**
     * @var mixed[] Handler config options
     * @phpstan-var Options
     */
    protected array $options;
    private bool $needs_type;
    /**
     * @param Client|Client8 $client  Elasticsearch Client object
     * @param mixed[]        $options Handler configuration
     *
     * @phpstan-param InputOptions $options
     */
    public function __construct(protected Client|Client8 $client, array $options = [], int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->options = array_merge([
            'index' => 'monolog',
            // Elastic index name
            'type' => '_doc',
            // Elastic document type
            'ignore_error' => false,
            // Suppress Elasticsearch exceptions
            'op_type' => 'index',
        ], $options);
        if ($this->client instanceof Client8 || $this->client::VERSION[0] === '7') {
            $this->needs_type = false;
            // force the type to _doc for ES8/ES7
            $this->options['type'] = '_doc';
        } else {
            $this->needs_type = true;
        }
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->bulk_send([$record->formatted]);
    }
    /**
     * @inheritDoc
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        if ($formatter instanceof Elasticsearch_Formatter) {
            return parent::set_formatter($formatter);
        }
        throw new InvalidArgumentException('ElasticsearchHandler is only compatible with ElasticsearchFormatter');
    }
    /**
     * Getter options
     *
     * @return mixed[]
     *
     * @phpstan-return Options
     */
    public function get_options(): array
    {
        return $this->options;
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Elasticsearch_Formatter($this->options['index'], $this->options['type']);
    }
    /**
     * @inheritDoc
     */
    public function handle_batch(array $records): void
    {
        $documents = $this->get_formatter()->format_batch($records);
        $this->bulk_send($documents);
    }
    /**
     * Use Elasticsearch bulk API to send list of documents
     *
     * @param  array<array<mixed>> $records Records + _index/_type keys
     * @throws \RuntimeException
     */
    protected function bulk_send(array $records): void
    {
        try {
            $params = ['body' => []];
            foreach ($records as $record) {
                $params['body'][] = [$this->options['op_type'] => $this->needs_type ? ['_index' => $record['_index'], '_type' => $record['_type']] : ['_index' => $record['_index']]];
                unset($record['_index'], $record['_type']);
                $params['body'][] = $record;
            }
            /** @var Elasticsearch */
            $responses = $this->client->bulk($params);
            if ($responses['errors'] === true) {
                throw $this->create_exception_from_responses($responses);
            }
        } catch (Throwable $e) {
            if (!$this->options['ignore_error']) {
                throw new RuntimeException('Error sending messages to Elasticsearch', 0, $e);
            }
        }
    }
    /**
     * Creates elasticsearch exception from responses array
     *
     * Only the first error is converted into an exception.
     *
     * @param mixed[]|Elasticsearch $responses returned by $this->client->bulk()
     */
    protected function create_exception_from_responses($responses): Throwable
    {
        foreach ($responses['items'] ?? [] as $item) {
            if (isset($item['index']['error'])) {
                return $this->create_exception_from_error($item['index']['error']);
            }
        }
        if (class_exists(Elastic_Invalid_Argument_Exception::class)) {
            return new Elastic_Invalid_Argument_Exception('Elasticsearch failed to index one or more records.');
        }
        if (class_exists(Elasticsearch_Runtime_Exception::class)) {
            return new Elasticsearch_Runtime_Exception('Elasticsearch failed to index one or more records.');
        }
        throw new \LogicException('Unsupported elastic search client version');
    }
    /**
     * Creates elasticsearch exception from error array
     *
     * @param mixed[] $error
     */
    protected function create_exception_from_error(array $error): Throwable
    {
        $previous = isset($error['caused_by']) ? $this->create_exception_from_error($error['caused_by']) : null;
        if (class_exists(Elastic_Invalid_Argument_Exception::class)) {
            return new Elastic_Invalid_Argument_Exception($error['type'] . ': ' . $error['reason'], 0, $previous);
        }
        if (class_exists(Elasticsearch_Runtime_Exception::class)) {
            return new Elasticsearch_Runtime_Exception($error['type'] . ': ' . $error['reason'], 0, $previous);
        }
        throw new \LogicException('Unsupported elastic search client version');
    }
}