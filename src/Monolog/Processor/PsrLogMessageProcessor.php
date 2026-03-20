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
namespace Monolog\Processor;

use Monolog\Log_Record;
use Monolog\Utils;
/**
 * Processes a record's message according to PSR-3 rules
 *
 * It replaces {foo} with the value from $context['foo']
 *
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Psr_Log_Message_Processor implements Processor_Interface
{
    public const SIMPLE_DATE = "Y-m-d\\TH:i:s.uP";
    /**
     * @param string|null $dateFormat              The format of the timestamp: one supported by DateTime::format
     * @param bool        $removeUsedContextFields If set to true the fields interpolated into message gets unset
     */
    public function __construct(private readonly ?string $date_format = null, private readonly bool $remove_used_context_fields = false)
    {
    }
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        if (!str_contains($record->message, '{')) {
            return $record;
        }
        $replacements = [];
        $context = $record->context;
        foreach ($context as $key => $val) {
            $placeholder = '{' . $key . '}';
            if (!str_contains($record->message, $placeholder)) {
                continue;
            }
            if (null === $val || \is_scalar($val) || \is_object($val) && method_exists($val, '__toString')) {
                $replacements[$placeholder] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                if (null === $this->date_format && $val instanceof \Monolog\Json_Serializable_Date_Time_Immutable) {
                    // handle monolog dates using __toString if no specific dateFormat was asked for
                    // so that it follows the useMicroseconds flag
                    $replacements[$placeholder] = (string) $val;
                } else {
                    $replacements[$placeholder] = $val->format($this->date_format ?? static::SIMPLE_DATE);
                }
            } elseif ($val instanceof \Unit_Enum) {
                $replacements[$placeholder] = $val instanceof \Backed_Enum ? $val->value : $val->name;
            } elseif (\is_object($val)) {
                $replacements[$placeholder] = '[object ' . Utils::get_class($val) . ']';
            } elseif (\is_array($val)) {
                $replacements[$placeholder] = 'array' . Utils::json_encode($val, null, true);
            } else {
                $replacements[$placeholder] = '[' . \gettype($val) . ']';
            }
            if ($this->remove_used_context_fields) {
                unset($context[$key]);
            }
        }
        return $record->with(message: strtr($record->message, $replacements), context: $context);
    }
}