<?php

namespace Dudlewebs\WPMCS\s3\Aws\Endpoint\UseDualstackEndpoint\Exception;

use Dudlewebs\WPMCS\s3\Aws\HasMonitoringEventsTrait;
use Dudlewebs\WPMCS\s3\Aws\MonitoringEventsInterface;
/**
 * Represents an error interacting with configuration for useDualstackRegion
 */
class ConfigurationException extends \RuntimeException implements MonitoringEventsInterface
{
    use HasMonitoringEventsTrait;
}
