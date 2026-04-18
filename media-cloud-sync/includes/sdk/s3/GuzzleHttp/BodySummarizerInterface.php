<?php

namespace Dudlewebs\WPMCS\s3\GuzzleHttp;

use Dudlewebs\WPMCS\s3\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message) : ?string;
}
