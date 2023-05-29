<?php

namespace Omniful\Integration\Api;

/**
 * @api
 */
interface ApiServiceInterface
{
    const API_INTEGRATION_NAME = "Omniful Core Creds.";

    /**
     * @return string
     */
    public function getToken();

    /**
     * @param \Magento\Integration\Model\Integration $integration
     * @return string
     */
    public function getIntegrationAccessToken(
        \Magento\Integration\Model\Integration $integration
    );

    /**
     * @param \Magento\Integration\Model\Integration $integration
     * @throws \Exception
     * @return $this|ApiServiceInterface
     */
    public function createAccessToken(
        \Magento\Integration\Model\Integration $integration
    );

    /**
     * @return $this
     */
    public function setupIntegration();

    /**
     * @throws \Exception
     * @return \Magento\Integration\Model\Integration
     */
    public function getIntegration();
}
