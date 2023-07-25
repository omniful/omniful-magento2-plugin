<?php

namespace Omniful\Integration\Model;

use Magento\Integration\Api\IntegrationServiceInterface;
use Magento\Integration\Api\OauthServiceInterface;
use Magento\Integration\Model\ConfigBasedIntegrationManager;
use Magento\Integration\Model\Integration;

use Omniful\Integration\Api\ApiServiceInterface;

class ApiService implements \Omniful\Integration\Api\ApiServiceInterface
{
    /**
     * @var IntegrationServiceInterface
     */
    protected $integrationService;

    /**
     * @var OauthServiceInterface
     */
    protected $oauthService;

    /**
     * @var ConfigBasedIntegrationManager
     */
    protected $integrationManager;

    /**
     * ApiService constructor.
     * @param OauthServiceInterface $oauthService
     * @param IntegrationServiceInterface $integrationService
     * @param ConfigBasedIntegrationManager $integrationManager
     */
    public function __construct(
        OauthServiceInterface $oauthService,
        IntegrationServiceInterface $integrationService,
        ConfigBasedIntegrationManager $integrationManager
    ) {
        $this->oauthService = $oauthService;
        $this->integrationService = $integrationService;
        $this->integrationManager = $integrationManager;
    }

    /**
     * Get Token
     *
     * @return string
     * @throws \Exception
     */
    public function getToken()
    {
        try {
            $integration = $this->getIntegration();
        } catch (\Exception $e) {
            $this->setupIntegration();
            $integration = $this->getIntegration();
        }
        $token = $this->getIntegrationAccessToken($integration);
        if (!$token) {
            $integration = $this->createAccessToken($integration);
            $token = $this->getIntegrationAccessToken($integration);
        }
        return $token;
    }

    /**
     * Get Integration
     *
     * @return Integration
     * @throws \Exception
     */
    public function getIntegration()
    {
        $integration = $this->integrationService->findByName(
            self::API_INTEGRATION_NAME
        );
        if ($integration && $integration->getIntegrationId()) {
            return $integration;
        }
        throw new \Magento\Framework\Exception\AlreadyExistsException(
            __("APIs Integration has not been setup correctly yet")
        );
    }

    /**
     * Setup Integration
     *
     * @return $this|ApiService
     */
    public function setupIntegration()
    {
        $this->integrationManager->processIntegrationConfig([
            ApiServiceInterface::API_INTEGRATION_NAME,
        ]);
        return $this;
    }

    /**
     * Get Integration Access Token
     *
     * @param Integration $integration
     * @return string
     */
    public function getIntegrationAccessToken(
        \Magento\Integration\Model\Integration $integration
    ) {
        $token = "";
        if ($integration->getStatus() == Integration::STATUS_ACTIVE &&
            $integration->getConsumerId()
        ) {
            $accessToken = $this->oauthService->getAccessToken(
                $integration->getConsumerId()
            );
            if ($accessToken && $accessToken->getToken()) {
                $token = $accessToken->getToken();
            }
        }
        return $token;
    }

    /**
     * Create Access Token
     *
     * @param Integration $integration
     * @return Integration|ApiServiceInterface|ApiService
     * @throws \Exception
     */
    public function createAccessToken(
        \Magento\Integration\Model\Integration $integration
    ) {
        if ($this->oauthService->createAccessToken(
            $integration->getConsumerId(),
            true
        )
        ) {
            $integration->setStatus(Integration::STATUS_ACTIVE)->save();
        }
        return $integration;
    }
}
