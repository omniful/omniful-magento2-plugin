<?php

namespace Omniful\Integration\Api;

use Magento\Framework\Exception\AuthenticationException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;

interface AdminTokenServiceInterface
{
    /**
     * Create access token for admin given the admin credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @throws InputException For invalid input
     * @throws AuthenticationException
     * @throws LocalizedException
     *
     * @return string Token created
     */
    public function getToken($username, $password);
}
