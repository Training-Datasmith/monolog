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

use Elastic\Transport\Exception\Transport_Exception;
use Elastica\Client;
use Elastica\Document;
use Elastica\Exception\Exception_Interface;
use Monolog\Formatter\Elastica_Formatter;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Elastic Search handler
 *
 * Usage example:
 *
 *    $client = new \Elastica\Client();
 *    $options = array(
 *        'index' => 'elastic_index_name',
 *        'type' => 'elastic_doc_type', Types have been removed in Elastica 7
 *    );
 *    $handler = new ElasticaHandler($client, $options);
 *    $log = new Logger('application');
 *    $log->pushHandler($handler);
 *
 * @author Jelle Vink <jelle.vink@gmail.com>
 * @phpstan-type Options array{
 *     index: string,
 *     type: string,
 *     ignore_error: bool
 * }
 * @phpstan-type InputOptions array{
 *     index?: string,
 *     type?: string,
 *     ignore_error?: bool
 * }
 */
class Elastica_Handler extends Abstract_Processing_Handler
{
    protected Client $client;
    /**
     * @var mixed[] Handler config options
     * @phpstan-var Options
     */
    protected array $options;
    /**
     * @param Client  $client  Elastica Client object
     * @param mixed[] $options Handler configuration
     *
     * @phpstan-param InputOptions $options
     */
    public function __construct(Client $client, array $options = [], int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->client = $client;
        $this->options = array_merge([
            'index' => 'monolog',
            // Elastic index name
            'type' => 'record',
            // Elastic document type
            'ignore_error' => false,
        ], $options);
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
        if ($formatter instanceof Elastica_Formatter) {
            return parent::set_formatter($formatter);
        }
        throw new \InvalidArgumentException('ElasticaHandler is only compatible with ElasticaFormatter');
    }
    /**
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
        return new Elastica_Formatter($this->options['index'], $this->options['type']);
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
     * @param Document[] $documents
     *
     * @throws \RuntimeException
     */
    protected function bulk_send(array $documents): void
    {
        try {
            $this->client->add_documents($documents);
        } catch (Exception_Interface|Transport_Exception $e) {
            if (!$this->options['ignore_error']) {
                throw new \RuntimeException('Error sending messages to Elasticsearch', 0, $e);
            }
        }
    }
}