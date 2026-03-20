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

use Monolog\Log_Record;
/**
 * Base Handler class providing the Handler structure, including processors and formatters
 *
 * Classes extending it should (in most cases) only implement write($record)
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 * @author Christophe Coevoet <stof@notk.org>
 */
abstract class Abstract_Processing_Handler extends Abstract_Handler implements Processable_Handler_Interface, Formattable_Handler_Interface
{
    use Processable_Handler_Trait;
    use Formattable_Handler_Trait;
    /**
     * @inheritDoc
     */
    public function handle(Log_Record $record): bool
    {
        if (!$this->is_handling($record)) {
            return false;
        }
        if (\count($this->processors) > 0) {
            $record = $this->process_record($record);
        }
        $record->formatted = $this->get_formatter()->format($record);
        $this->write($record);
        return false === $this->bubble;
    }
    /**
     * Writes the (already formatted) record down to the log of the implementing handler
     */
    abstract protected function write(Log_Record $record): void;
    public function reset(): void
    {
        parent::reset();
        $this->reset_processors();
    }
}