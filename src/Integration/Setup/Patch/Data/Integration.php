<?php

namespace Omniful\Integration\Setup\Patch\Data;

use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

use Omniful\Integration\Api\ApiServiceInterface;

class Integration implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    protected $moduleDataSetup;

    /**
     * @var ApiServiceInterface
     */
    protected $apiService;

    /**
     * Integration constructor.
     *
     * @param ApiServiceInterface $apiService
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        ApiServiceInterface $apiService,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->apiService = $apiService;
        $this->moduleDataSetup = $moduleDataSetup;
    }

    /**
     * Apply
     *
     * @return Integration|void
     * @throws \Exception
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
     * Get Dependencies
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get Aliases
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
