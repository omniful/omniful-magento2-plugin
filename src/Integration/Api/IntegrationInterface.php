<?php

namespace Omniful\Integration\Api;

/**
 * @api
 */
interface IntegrationInterface
{
    /**
     * Get Token
     *
     * @return string
     */
    public function getToken();
}
