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

use Aws\Dynamo_Db\Dynamo_Db_Client;
use Aws\Dynamo_Db\Marshaler;
use Aws\Sdk;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Scalar_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Amazon DynamoDB handler (http://aws.amazon.com/dynamodb/)
 *
 * @link https://github.com/aws/aws-sdk-php/
 * @author Andrew Lawson <adlawson@gmail.com>
 */
class Dynamo_Db_Handler extends Abstract_Processing_Handler
{
    public const DATE_FORMAT = 'Y-m-d\TH:i:s.uO';
    protected Dynamo_Db_Client $client;
    protected Marshaler $marshaler;
    public function __construct(Dynamo_Db_Client $client, protected string $table, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        $this->marshaler = new Marshaler();
        $this->client = $client;
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $filtered = $this->filter_empty_fields($record->formatted);
        $formatted = $this->marshaler->marshal_item($filtered);
        $this->client->put_item(['TableName' => $this->table, 'Item' => $formatted]);
    }
    /**
     * @param  mixed[] $record
     * @return mixed[]
     */
    protected function filter_empty_fields(array $record): array
    {
        return array_filter($record, fn($value) => [] !== $value);
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Scalar_Formatter(self::DATE_FORMAT);
    }
}