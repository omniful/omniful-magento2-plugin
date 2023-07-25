<?php

namespace Omniful\Integration\Api;

use Magento\Integration\Model\Integration;

/**
 * @api
 */
interface ApiServiceInterface
{
    public const API_INTEGRATION_NAME = "Omniful Core Creds.";

    /**
     * Get Token
     *
     * @return mixed
     */
    public function getToken();

    /**
     * Get Integration Access Token
     *
     * @param Integration $integration
     * @return mixed
     */
    public function getIntegrationAccessToken(
        Integration $integration
    );

    /**
     * Create Access Token
     *
     * @param Integration $integration
     * @throws \Exception
     * @return $this|ApiServiceInterface
     */
    public function createAccessToken(
        Integration $integration
    );

    /**
     * Setup Integration
     *
     * @return mixed
     */
    public function setupIntegration();

    /**
     * Get Integration
     *
     * @return mixed
     */
    public function getIntegration();
}
