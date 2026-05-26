<?php

namespace Dudlewebs\WPMCS\GCP\Google\ApiCore;

use Dudlewebs\WPMCS\GCP\GuzzleHttp\Psr7\Utils;
use Dudlewebs\WPMCS\GCP\Psr\Http\Message\UriInterface;
/**
 * @internal
 */
class InsecureRequestBuilder extends RequestBuilder
{
    /**
     * @param string $path
     * @param array $queryParams
     * @return UriInterface
     */
    protected function buildUri(string $path, array $queryParams)
    {
        $uri = Utils::uriFor(\sprintf('http://%s%s', $this->baseUri, $path));
        if ($queryParams) {
            $uri = $this->buildUriWithQuery($uri, $queryParams);
        }
        return $uri;
    }
}
