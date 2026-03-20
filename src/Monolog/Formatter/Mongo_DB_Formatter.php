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
namespace Monolog\Formatter;

use Mongo_Db\BSON\Type;
use Mongo_Db\BSON\Utc_Date_Time;
use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Formats a record for use with the MongoDBHandler.
 *
 * @author Florian Plattner <me@florianplattner.de>
 */
class Mongo_Db_Formatter implements Formatter_Interface
{
    private readonly int $max_nesting_level;
    /**
     * @param int  $maxNestingLevel        0 means infinite nesting, the $record itself is level 1, $record->context is 2
     * @param bool $exceptionTraceAsString set to false to log exception traces as a sub documents instead of strings
     */
    public function __construct(int $max_nesting_level = 3, private readonly bool $exception_trace_as_string = true)
    {
        $this->max_nesting_level = max($max_nesting_level, 0);
    }
    /**
     * @inheritDoc
     *
     * @return mixed[]
     */
    public function format(Log_Record $record): array
    {
        /** @var mixed[] $res */
        $res = $this->format_array($record->to_array());
        return $res;
    }
    /**
     * @inheritDoc
     *
     * @return array<mixed[]>
     */
    public function format_batch(array $records): array
    {
        $formatted = [];
        foreach ($records as $key => $record) {
            $formatted[$key] = $this->format($record);
        }
        return $formatted;
    }
    /**
     * @param  mixed[]        $array
     * @return mixed[]|string Array except when max nesting level is reached then a string "[...]"
     */
    protected function format_array(array $array, int $nesting_level = 0): string|array
    {
        if ($this->max_nesting_level > 0 && $nesting_level > $this->max_nesting_level) {
            return '[...]';
        }
        foreach ($array as $name => $value) {
            if ($value instanceof \DateTimeInterface) {
                $array[$name] = $this->format_date($value, $nesting_level + 1);
            } elseif ($value instanceof \Throwable) {
                $array[$name] = $this->format_exception($value, $nesting_level + 1);
            } elseif (\is_array($value)) {
                $array[$name] = $this->format_array($value, $nesting_level + 1);
            } elseif (\is_object($value) && !$value instanceof Type) {
                $array[$name] = $this->format_object($value, $nesting_level + 1);
            }
        }
        return $array;
    }
    /**
     * @param  mixed          $value
     * @return mixed[]|string
     */
    protected function format_object(object $value, int $nesting_level)
    {
        $object_vars = get_object_vars($value);
        $object_vars['class'] = Utils::get_class($value);
        return $this->format_array($object_vars, $nesting_level);
    }
    /**
     * @return mixed[]|string
     */
    protected function format_exception(\Throwable $exception, int $nesting_level)
    {
        $formatted_exception = ['class' => Utils::get_class($exception), 'message' => $exception->get_message(), 'code' => (int) $exception->get_code(), 'file' => $exception->get_file() . ':' . $exception->get_line()];
        if ($this->exception_trace_as_string === true) {
            $formatted_exception['trace'] = $exception->get_trace_as_string();
        } else {
            $formatted_exception['trace'] = $exception->get_trace();
        }
        return $this->format_array($formatted_exception, $nesting_level);
    }
    protected function format_date(\DateTimeInterface $value, int $nesting_level): Utc_Date_Time
    {
        return new Utc_Date_Time((int) floor((float) $value->format('U.u') * 1000));
    }
}