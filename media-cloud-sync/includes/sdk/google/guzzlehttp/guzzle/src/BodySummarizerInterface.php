<?php

namespace Dudlewebs\WPMCS\GuzzleHttp;

use Dudlewebs\WPMCS\Psr\Http\Message\MessageInterface;
interface BodySummarizerInterface
{
    /**
     * Returns a summarized message body.
     */
    public function summarize(MessageInterface $message): ?string;
}
