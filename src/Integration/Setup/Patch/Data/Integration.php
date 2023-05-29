<?php

namespace Omniful\Integration\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

use Omniful\Integration\Api\ApiServiceInterface;

class Integration implements DataPatchInterface
{
    protected $moduleDataSetup;

    /**
     * @var ApiServiceInterface
     */
    protected $apiService;

    public function __construct(
        ApiServiceInterface $apiService,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->apiService = $apiService;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        $this->moduleDataSetup->startSetup();

        $this->apiService->setupIntegration();
        $integration = $this->apiService->getIntegration();
        $this->apiService->createAccessToken($integration);

        $this->moduleDataSetup->endSetup();
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
