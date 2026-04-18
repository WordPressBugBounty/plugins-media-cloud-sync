<?php

namespace Dudlewebs\WPMCS\s3\Aws\Arn\S3;

use Dudlewebs\WPMCS\s3\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface OutpostsArnInterface extends ArnInterface
{
    public function getOutpostId();
}
