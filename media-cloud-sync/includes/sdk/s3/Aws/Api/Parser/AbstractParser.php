<?php

namespace Dudlewebs\WPMCS\s3\Aws\Api\Parser;

use Dudlewebs\WPMCS\s3\Aws\Api\Service;
use Dudlewebs\WPMCS\s3\Aws\Api\StructureShape;
use Dudlewebs\WPMCS\s3\Aws\CommandInterface;
use Dudlewebs\WPMCS\s3\Aws\ResultInterface;
use Dudlewebs\WPMCS\s3\Psr\Http\Message\ResponseInterface;
use Dudlewebs\WPMCS\s3\Psr\Http\Message\StreamInterface;
/**
 * @internal
 */
abstract class AbstractParser
{
    /** @var \Aws\Api\Service Representation of the service API*/
    protected $api;
    /** @var callable */
    protected $parser;
    /**
     * @param Service $api Service description.
     */
    public function __construct(Service $api)
    {
        $this->api = $api;
    }
    /**
     * @param CommandInterface  $command  Command that was executed.
     * @param ResponseInterface $response Response that was received.
     *
     * @return ResultInterface
     */
    public abstract function __invoke(CommandInterface $command, ResponseInterface $response);
    public abstract function parseMemberFromStream(StreamInterface $stream, StructureShape $member, $response);
}
