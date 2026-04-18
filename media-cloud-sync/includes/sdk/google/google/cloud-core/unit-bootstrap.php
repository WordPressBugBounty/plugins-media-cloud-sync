<?php

namespace Dudlewebs\WPMCS;

use Dudlewebs\WPMCS\Google\ApiCore\Testing\MessageAwareArrayComparator;
use Dudlewebs\WPMCS\Google\ApiCore\Testing\ProtobufMessageComparator;
use Dudlewebs\WPMCS\Google\ApiCore\Testing\ProtobufGPBEmptyComparator;
\date_default_timezone_set('UTC');
\Dudlewebs\WPMCS\SebastianBergmann\Comparator\Factory::getInstance()->register(new MessageAwareArrayComparator());
\Dudlewebs\WPMCS\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufMessageComparator());
\Dudlewebs\WPMCS\SebastianBergmann\Comparator\Factory::getInstance()->register(new ProtobufGPBEmptyComparator());
