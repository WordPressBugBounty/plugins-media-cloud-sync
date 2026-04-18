<?php

/**
 * This file is part of the ramsey/uuid library
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) Ben Ramsey <ben@benramsey.com>
 * @license http://opensource.org/licenses/MIT MIT
 */
declare (strict_types=1);
namespace Dudlewebs\WPMCS\Ramsey\Uuid\Nonstandard;

use Dudlewebs\WPMCS\Ramsey\Uuid\Codec\CodecInterface;
use Dudlewebs\WPMCS\Ramsey\Uuid\Converter\NumberConverterInterface;
use Dudlewebs\WPMCS\Ramsey\Uuid\Converter\TimeConverterInterface;
use Dudlewebs\WPMCS\Ramsey\Uuid\Uuid as BaseUuid;
/**
 * Nonstandard\Uuid is a UUID that doesn't conform to RFC 4122
 *
 * @psalm-immutable
 */
final class Uuid extends BaseUuid
{
    public function __construct(Fields $fields, NumberConverterInterface $numberConverter, CodecInterface $codec, TimeConverterInterface $timeConverter)
    {
        parent::__construct($fields, $numberConverter, $codec, $timeConverter);
    }
}
