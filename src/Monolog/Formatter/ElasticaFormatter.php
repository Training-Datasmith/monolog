<?php declare(strict_types=1);

/*
 * This file is part of the Monolog package.
 *
 * (c) Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Monolog\Formatter;

use Elastica\Document;
use Monolog\LogRecord;

/**
 * Format a log message into an Elastica Document
 *
 * @author Jelle Vink <jelle.vink@gmail.com>
 */
class ElasticaFormatter extends NormalizerFormatter
{
    /**
     * @param string  $index Elastic Search index name
     * @param ?string $type  Elastic Search document type, deprecated as of Elastica 7
     */
    public function __construct(protected string $index, protected string|null $type)
    {
        // elasticsearch requires a ISO 8601 format date with optional millisecond precision.
        parent::__construct('Y-m-d\TH:i:s.uP');
    }

    /**
     * @inheritDoc
     */
    public function format(LogRecord $record): \Elastica\Document
    {
        $record = parent::format($record);

        return $this->getDocument($record);
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    /**
     * @deprecated since Elastica 7 type has no effect
     */
    public function getType(): string
    {
        /** @phpstan-ignore-next-line */
        return $this->type;
    }

    /**
     * Convert a log message into an Elastica Document
     *
     * @param mixed[] $record
     */
    protected function getDocument(array $record): Document
    {
        $document = new Document();
        $document->setData($record);
        $document->setIndex($this->index);

        return $document;
    }
}
