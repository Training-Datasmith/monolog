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

use Monolog\Formatter\Formatter_Interface;
/**
 * Interface to describe loggers that have a formatter
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
interface Formattable_Handler_Interface
{
    /**
     * Sets the formatter.
     *
     * @return HandlerInterface self
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface;
    /**
     * Gets the formatter.
     */
    public function get_formatter(): Formatter_Interface;
}