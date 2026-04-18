<?php

namespace Dudlewebs\WPMCS\s3\GuzzleHttp;

use Dudlewebs\WPMCS\s3\Psr\Http\Message\RequestInterface;
use Dudlewebs\WPMCS\s3\Psr\Http\Message\ResponseInterface;
interface MessageFormatterInterface
{
    /**
     * Returns a formatted message string.
     *
     * @param RequestInterface       $request  Request that was sent
     * @param ResponseInterface|null $response Response that was received
     * @param \Throwable|null        $error    Exception that was received
     */
    public function format(RequestInterface $request, ?ResponseInterface $response = null, ?\Throwable $error = null) : string;
}
