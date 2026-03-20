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

use Gelf\Publisher_Interface;
use Monolog\Formatter\Formatter_Interface;
use Monolog\Formatter\Gelf_Message_Formatter;
use Monolog\Level;
use Monolog\Log_Record;
/**
 * Handler to send messages to a Graylog2 (http://www.graylog2.org) server
 *
 * @author Matt Lehner <mlehner@gmail.com>
 * @author Benjamin Zikarsky <benjamin@zikarsky.de>
 */
class Gelf_Handler extends Abstract_Processing_Handler
{
    /**
     * @var PublisherInterface the publisher object that sends the message to the server
     */
    protected Publisher_Interface $publisher;
    /**
     * @param PublisherInterface $publisher a gelf publisher object
     */
    public function __construct(Publisher_Interface $publisher, int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->publisher = $publisher;
    }
    /**
     * @inheritDoc
     */
    protected function write(Log_Record $record): void
    {
        $this->publisher->publish($record->formatted);
    }
    /**
     * @inheritDoc
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Gelf_Message_Formatter();
    }
}