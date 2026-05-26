<?php

namespace Dudlewebs\WPMCS\GCP;

use Dudlewebs\WPMCS\GCP\DG\BypassFinals;
use Dudlewebs\WPMCS\GCP\Google\ApiCore\Testing\MessageAwareArrayComparator;
use Dudlewebs\WPMCS\GCP\Google\ApiCore\Testing\ProtobufGPBEmptyComparator;
use Dudlewebs\WPMCS\GCP\Google\ApiCore\Testing\ProtobufMessageComparator;
\date_default_timezone_set('UTC');
\Dudlewebs\WPMCS\GCP\SebastianBergmann\Comparator\Factory::getInstance()->register(new MessageAwareArrayComparator());
\Dudlewebs\WPMCS\GCP\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufMessageComparator());
\Dudlewebs\WPMCS\GCP\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufGPBEmptyComparator());
// Make sure that while testing we bypass the `final` keyword for the GAPIC client.
// Only run this if the individual component has the helper package installed
if (\class_exists(BypassFinals::class)) {
    BypassFinals::enable();
}
