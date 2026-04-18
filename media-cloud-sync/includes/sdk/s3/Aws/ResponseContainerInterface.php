<?php

namespace Dudlewebs\WPMCS\s3\Aws;

use Dudlewebs\WPMCS\s3\Psr\Http\Message\ResponseInterface;
interface ResponseContainerInterface
{
    /**
     * Get the received HTTP response if any.
     *
     * @return ResponseInterface|null
     */
    public function getResponse();
}
