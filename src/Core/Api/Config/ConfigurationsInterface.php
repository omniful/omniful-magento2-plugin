<?php

namespace Omniful\Core\Api\Config;

/**
 * ConfigurationsInterface for third party modules
 */
interface ConfigurationsInterface
{
    /**
     * UpdateConfig
     *
     * @param array $configData An array containing the configuration data to be updated
     *
     * @return mixed
     */
    public function updateConfig(array $configData);
}