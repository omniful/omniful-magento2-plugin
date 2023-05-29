<?php

namespace Omniful\Integration\Model;

use Omniful\Integration\Api\ApiServiceInterface;

class Integration implements \Omniful\Integration\Api\IntegrationInterface
{
    /**
     * @var ApiServiceInterface
     */
    protected $apiService;

    public function __construct(ApiServiceInterface $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->apiService->getToken();
    }
}
