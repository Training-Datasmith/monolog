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
use Monolog\Resettable_Interface;
/**
 * Adds a unique identifier into records
 *
 * @author Simon Mönch <sm@webfactory.de>
 */
class Uid_Processor implements Processor_Interface, Resettable_Interface
{
    /** @var non-empty-string */
    private string $uid;
    /**
     * @param int<1, 32> $length
     */
    public function __construct(int $length = 7)
    {
        if ($length < 1) {
            throw new \InvalidArgumentException('The uid length must be an integer between 1 and 32');
        }
        $this->uid = $this->generate_uid($length);
    }
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        $record->extra['uid'] = $this->uid;
        return $record;
    }
    public function get_uid(): string
    {
        return $this->uid;
    }
    public function reset(): void
    {
        $this->uid = $this->generate_uid(\strlen($this->uid));
    }
    /**
     * @param  positive-int     $length
     * @return non-empty-string
     */
    private function generate_uid(int $length): string
    {
        return substr(bin2hex(random_bytes((int) ceil($length / 2))), 0, $length);
    }
}