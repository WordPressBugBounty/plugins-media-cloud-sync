<?php

namespace Dudlewebs\WPMCS\s3\Aws\Auth\Exception;

use Dudlewebs\WPMCS\s3\Aws\HasMonitoringEventsTrait;
use Dudlewebs\WPMCS\s3\Aws\MonitoringEventsInterface;
/**
 * Represents an error when attempting to resolve authentication.
 */
class UnresolvedAuthSchemeException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
