<?php

namespace Dudlewebs\WPMCS;

use Dudlewebs\WPMCS\DG\BypassFinals;
use Dudlewebs\WPMCS\Google\ApiCore\Testing\MessageAwareArrayComparator;
use Dudlewebs\WPMCS\Google\ApiCore\Testing\ProtobufGPBEmptyComparator;
use Dudlewebs\WPMCS\Google\ApiCore\Testing\ProtobufMessageComparator;
\date_default_timezone_set('UTC');
\Dudlewebs\WPMCS\SebastianBergmann\Comparator\Factory::getInstance()->register(new MessageAwareArrayComparator());
\Dudlewebs\WPMCS\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufMessageComparator());
\Dudlewebs\WPMCS\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufGPBEmptyComparator());
// Make sure that while testing we bypass the `final` keyword for the GAPIC client.
// Only run this if the individual component has the helper package installed
if (\class_exists(BypassFinals::class)) {
    BypassFinals::enable();
}
