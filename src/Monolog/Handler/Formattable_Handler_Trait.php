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
use Monolog\Formatter\Line_Formatter;
/**
 * Helper trait for implementing FormattableInterface
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
trait Formattable_Handler_Trait
{
    protected Formatter_Interface|null $formatter = null;
    /**
     * @inheritDoc
     */
    public function set_formatter(Formatter_Interface $formatter): Handler_Interface
    {
        $this->formatter = $formatter;
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function get_formatter(): Formatter_Interface
    {
        if (null === $this->formatter) {
            $this->formatter = $this->get_default_formatter();
        }
        return $this->formatter;
    }
    /**
     * Gets the default formatter.
     *
     * Overwrite this if the LineFormatter is not a good default for your handler.
     */
    protected function get_default_formatter(): Formatter_Interface
    {
        return new Line_Formatter();
    }
}