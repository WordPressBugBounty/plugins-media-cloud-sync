<?php

namespace Dudlewebs\WPMCS\s3\Aws\ClientSideMonitoring;

use Dudlewebs\WPMCS\s3\Aws\CommandInterface;
use Dudlewebs\WPMCS\s3\Aws\Exception\AwsException;
use Dudlewebs\WPMCS\s3\Aws\ResultInterface;
use Dudlewebs\WPMCS\s3\GuzzleHttp\Psr7\Request;
use Dudlewebs\WPMCS\s3\Psr\Http\Message\RequestInterface;
/**
 * @internal
 */
interface MonitoringMiddlewareInterface
{
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param RequestInterface $request
     * @return array
     */
    public static function getRequestData(RequestInterface $request);
    /**
     * Data for event properties to be sent to the monitoring agent.
     *
     * @param ResultInterface|AwsException|\Exception $klass
     * @return array
     */
    public static function getResponseData($klass);
    public function __invoke(CommandInterface $cmd, RequestInterface $request);
}
