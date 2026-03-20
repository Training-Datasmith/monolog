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

use Doctrine\Couch_Db\Couch_Db_Client;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Normalizer_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * CouchDB handler for Doctrine CouchDB ODM
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class Doctrine_Couch_Db_Handler extends Abstract_Processing_Handler
{
    private readonly Couch_Db_Client $client;
    public function __construct(Couch_Db_Client $client, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        $this->client = $client;
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->client->post_document($record->formatted);
    }
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Normalizer_Formatter();
    }
}