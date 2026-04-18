<?php

namespace Dudlewebs\WPMCS\s3\Aws\Signature;

use Dudlewebs\WPMCS\s3\Aws\Credentials\CredentialsInterface;
use Dudlewebs\WPMCS\s3\Psr\Http\Message\RequestInterface;
/**
 * Provides anonymous client access (does not sign requests).
 */
class AnonymousSignature implements SignatureInterface
{
    /**
     * /** {@inheritdoc}
     */
    public function signRequest(RequestInterface $request, CredentialsInterface $credentials)
    {
        return $request;
    }
    /**
     * /** {@inheritdoc}
     */
    public function presign(RequestInterface $request, CredentialsInterface $credentials, $expires, array $options = [])
    {
        return $request;
    }
}
