<?php

namespace Omniful\Core\Api\Config;

/**
 * ConfigurationsInterface for third party modules
 */
interface ConfigurationsInterface
{
    /**
     * getOmnifulConfigs
     *
     * @param array $configData An array containing the configuration data to be updated
     *
     * @return string[] An array containing the details of the config.
     */
    public function getOmnifulConfigs();

    /**
     * UpdateConfig
     *
     * @return string[] An array containing the details of the config.
     */
    public function updateConfig();
}
