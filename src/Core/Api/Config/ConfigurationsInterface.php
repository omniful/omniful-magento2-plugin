<?php

namespace Omniful\Core\Api\Config;

/**
 * ConfigurationsInterface for third party modules
 */
interface ConfigurationsInterface
{

    /**
     * Get Omniful Config
     *
     * @return mixed
     */
    public function updateConfig();

    /**
     * Get Omniful Configs
     *
     * @return mixed|void
     */
    public function getOmnifulConfigs();
}
