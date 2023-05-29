<?php

namespace Omniful\Integration\Api;

interface AdminTokenServiceInterface
{
    /**
     * Create access token for admin given the admin credentials.
     *
     * @param string $username
     * @param string $password
     *
     * @throws \Magento\Framework\Exception\InputException For invalid input
     * @throws \Magento\Framework\Exception\AuthenticationException
     * @throws \Magento\Framework\Exception\LocalizedException
     *
     * @return string Token created
     */
    public function getToken($username, $password);
}
