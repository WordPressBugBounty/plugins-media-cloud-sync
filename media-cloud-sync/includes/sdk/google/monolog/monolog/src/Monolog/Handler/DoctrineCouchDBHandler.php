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
namespace Dudlewebs\WPMCS\GCP\Monolog\Handler;

use Dudlewebs\WPMCS\GCP\Monolog\Level;
use Dudlewebs\WPMCS\GCP\Monolog\Formatter\NormalizerFormatter;
use Dudlewebs\WPMCS\GCP\Monolog\Formatter\FormatterInterface;
use Dudlewebs\WPMCS\GCP\Doctrine\CouchDB\CouchDBClient;
use Dudlewebs\WPMCS\GCP\Monolog\LogRecord;
/**
 * CouchDB handler for Doctrine CouchDB ODM
 *
 * @author Markus Bachmann <markus.bachmann@bachi.biz>
 */
class DoctrineCouchDBHandler extends AbstractProcessingHandler
{
    private CouchDBClient $client;
    public function __construct(CouchDBClient $client, int|string|Level $level = Level::Debug, bool $bubble = \true)
    {
        $this->client = $client;
        parent::__construct($level, $bubble);
    }
    /**
     * @inheritDoc
     */
    protected function write(LogRecord $record) : void
    {
        $this->client->postDocument($record->formatted);
    }
    protected function getDefaultFormatter() : FormatterInterface
    {
        return new NormalizerFormatter();
    }
}
