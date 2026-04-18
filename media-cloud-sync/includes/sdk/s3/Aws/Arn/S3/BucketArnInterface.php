<?php

namespace Dudlewebs\WPMCS\s3\Aws\Arn\S3;

use Dudlewebs\WPMCS\s3\Aws\Arn\ArnInterface;
/**
 * @internal
 */
interface BucketArnInterface extends ArnInterface
{
    public function getBucketName();
}
