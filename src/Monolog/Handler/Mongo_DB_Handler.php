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

use Mongo_Db\Client;
use Mongo_Db\Collection;
use Mongo_Db\Driver\Bulk_Write;
use Mongo_Db\Driver\Manager;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Mongo_Db_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Logs to a MongoDB database.
 *
 * Usage example:
 *
 *   $log = new \Monolog\Logger('application');
 *   $client = new \MongoDB\Client('mongodb://localhost:27017');
 *   $mongodb = new \Monolog\Handler\MongoDBHandler($client, 'logs', 'prod');
 *   $log->pushHandler($mongodb);
 *
 * The above examples uses the MongoDB PHP library's client class; however, the
 * MongoDB\Driver\Manager class from ext-mongodb is also supported.
 */
class Mongo_Db_Handler extends Abstract_Processing_Handler
{
    private Collection $collection;
    private Client|Manager $manager;
    private string|null $namespace = null;
    /**
     * Constructor.
     *
     * @param Client|Manager $mongodb    MongoDB library or driver client
     * @param string         $database   Database name
     * @param string         $collection Collection name
     */
    public function __construct(Client|Manager $mongodb, string $database, string $collection, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        if ($mongodb instanceof Client) {
            $this->collection = method_exists($mongodb, 'getCollection') ? $mongodb->get_collection($database, $collection) : $mongodb->select_collection($database, $collection);
        } else {
            $this->manager = $mongodb;
            $this->namespace = $database . '.' . $collection;
        }
        parent::__construct($level, $bubble);
    }
    protected function write(Log_Record $record): void
    {
        if (isset($this->collection)) {
            $this->collection->insert_one($record->formatted);
        }
        if (isset($this->manager, $this->namespace)) {
            $bulk = new Bulk_Write();
            $bulk->insert($record->formatted);
            $this->manager->execute_bulk_write($this->namespace, $bulk);
        }
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Mongo_Db_Formatter();
    }
}