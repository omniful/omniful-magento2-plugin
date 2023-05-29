<?php

namespace Omniful\Integration\Model;

use Magento\User\Model\User as UserModel;
use Magento\Integration\Model\CredentialsValidator;
use Magento\Integration\Model\Oauth\Token as Token;
use Magento\Framework\Exception\AuthenticationException;
use Magento\Integration\Model\Oauth\Token\RequestThrottler;
use Magento\Integration\Model\Oauth\TokenFactory as TokenModelFactory;

class AdminTokenService implements
    \Omniful\Integration\Api\AdminTokenServiceInterface
{
    /**
     * Token Model.
     *
     * @var TokenModelFactory
     */
    private $tokenModelFactory;

    /**
     * User Model.
     *
     * @var UserModel
     */
    private $userModel;

    /**
     * @var \Magento\Integration\Model\CredentialsValidator
     */
    private $validatorHelper;

    /**
     * @var RequestThrottler
     */
    private $requestThrottler;

    protected $adminToken;

    /**
     * Initialize service.
     */
    public function __construct(
        UserModel $userModel,
        TokenModelFactory $tokenModelFactory,
        CredentialsValidator $validatorHelper,
        \Omniful\Integration\Api\ApiServiceInterface $adminToken
    ) {
        $this->userModel = $userModel;
        $this->adminToken = $adminToken;
        $this->validatorHelper = $validatorHelper;
        $this->tokenModelFactory = $tokenModelFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function getIntegrationToken($username, $password)
    {
        $tokenData["adminToken"] = $this->adminToken->getToken();

        $returnData[] = $tokenData;

        return $returnData;
    }

    /**
     * {@inheritdoc}
     */
    public function getToken($username, $password)
    {
        $token = "";
        $status = false;
        $message = "";
        try {
            $token = $this->createAdminAccessToken($username, $password);
            $message = __("success");
            $status = true;
        } catch (AuthenticationException $e) {
            $message = $e->getMessage();
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        $tokenData["status"] = $status;
        $tokenData["message"] = $message;
        $tokenData["adminToken"] = $token;
        $returnData[] = $tokenData;

        return $returnData;
    }

    /**
     * {@inheritdoc}
     */
    public function createAdminAccessToken($username, $password)
    {
        $this->validatorHelper->validate($username, $password);
        $this->getRequestThrottler()->throttle(
            $username,
            RequestThrottler::USER_TYPE_ADMIN
        );
        $this->userModel->login($username, $password);
        if (!$this->userModel->getId()) {
            $this->getRequestThrottler()->logAuthenticationFailure(
                $username,
                RequestThrottler::USER_TYPE_ADMIN
            );
            throw new AuthenticationException(
                __(
                    "The account sign-in was incorrect or your account is disabled temporarily. " .
                        "Please wait and try again later."
                )
            );
        }
        $this->getRequestThrottler()->resetAuthenticationFailuresCount(
            $username,
            RequestThrottler::USER_TYPE_ADMIN
        );

        return $this->tokenModelFactory
            ->create()
            ->createAdminToken($this->userModel->getId())
            ->getToken();
    }

    /**
     * Get request throttler instance.
     *
     * @return RequestThrottler
     *
     * @deprecated 100.0.4
     */
    private function getRequestThrottler()
    {
        if (!$this->requestThrottler instanceof RequestThrottler) {
            return \Magento\Framework\App\ObjectManager::getInstance()->get(
                RequestThrottler::class
            );
        }

        return $this->requestThrottler;
    }
}
