<?php

namespace Dudlewebs\WPMCS\s3\Aws\Endpoint\UseFipsEndpoint;

use Dudlewebs\WPMCS\s3\Aws;
use Dudlewebs\WPMCS\s3\Aws\Endpoint\UseFipsEndpoint\Exception\ConfigurationException;
class Configuration implements ConfigurationInterface
{
    private $useFipsEndpoint;
    public function __construct($useFipsEndpoint)
    {
        $this->useFipsEndpoint = Aws\boolean_value($useFipsEndpoint);
        if (\is_null($this->useFipsEndpoint)) {
            throw new ConfigurationException("'use_fips_endpoint' config option" . " must be a boolean value.");
        }
    }
    /**
     * {@inheritdoc}
     */
    public function isUseFipsEndpoint()
    {
        return $this->useFipsEndpoint;
    }
    /**
     * {@inheritdoc}
     */
    public function toArray()
    {
        return ['use_fips_endpoint' => $this->isUseFipsEndpoint()];
    }
}
