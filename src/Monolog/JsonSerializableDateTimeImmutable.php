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
namespace Monolog;

use DateTimeZone;
/**
 * Overrides default json encoding of date time objects
 *
 * @author Menno Holtkamp
 * @author Jordi Boggiano <j.boggiano@seld.be>
 */
class Json_Serializable_Date_Time_Immutable extends \DateTimeImmutable implements \JsonSerializable, \Stringable
{
    public function __construct(private readonly bool $use_microseconds, ?DateTimeZone $timezone = null)
    {
        // if you like to use a custom time to pass to Logger::addRecord directly,
        // call modify() or setTimestamp() on this instance to change the date after creating it
        parent::__construct('now', $timezone);
    }
    public function jsonSerialize(): string
    {
        if ($this->use_microseconds) {
            return $this->format('Y-m-d\TH:i:s.uP');
        }
        return $this->format('Y-m-d\TH:i:sP');
    }
    public function __toString(): string
    {
        return $this->jsonSerialize();
    }
}