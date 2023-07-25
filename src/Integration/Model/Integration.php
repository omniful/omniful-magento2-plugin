<?php

namespace Omniful\Integration\Model;

use Omniful\Integration\Api\ApiServiceInterface;

class Integration implements \Omniful\Integration\Api\IntegrationInterface
{
    /**
     * @var ApiServiceInterface
     */
    protected $apiService;

    /**
     * Integration constructor.
     *
     * @param ApiServiceInterface $apiService
     */
    public function __construct(ApiServiceInterface $apiService)
    {
        $this->apiService = $apiService;
    }

    /**
     * GetToken
     *
     * @return string
     */
    public function getToken()
    {
        return $this->apiService->getToken();
    }
}
