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
/**
 * Adds a tags array into record
 *
 * @author Martijn Riemers
 */
class Tag_Processor implements Processor_Interface
{
    /** @var string[] */
    private array $tags;
    /**
     * @param string[] $tags
     */
    public function __construct(array $tags = [])
    {
        $this->set_tags($tags);
    }
    /**
     * @param  string[] $tags
     * @return $this
     */
    public function add_tags(array $tags = []): self
    {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }
    /**
     * @param  string[] $tags
     * @return $this
     */
    public function set_tags(array $tags = []): self
    {
        $this->tags = $tags;
        return $this;
    }
    /**
     * @inheritDoc
     */
    public function __invoke(Log_Record $record): Log_Record
    {
        $record->extra['tags'] = $this->tags;
        return $record;
    }
}