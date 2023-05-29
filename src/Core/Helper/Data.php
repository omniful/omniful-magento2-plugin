<?php

namespace Omniful\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Class Data.
 */
class Data extends AbstractHelper
{
    const SUCCESS_HTTP_CODE = 200;
    const FAILED_HTTP_CODE = 204;
    const ERROR_HTTP_CODE = 500;
    const EMPTY_CONTENT_CONTAINS = "No Data are available.";

    /**
     * @var StoreManagerInterface
     */
    public $_storeManagerInterface;

    public function __construct(Context $context)
    {
        parent::__construct($context);
    }

    public function getIsActive()
    {
        return (bool) $this->scopeConfig->getValue(
            "omniful_core/general/active",
            ScopeInterface::SCOPE_STORE
        );
    }
    public function isOrderShipButtonDisabled()
    {
        return (bool) $this->scopeConfig->getValue(
            "omniful_core/general/disable_ship_button",
            ScopeInterface::SCOPE_STORE
        );
    }
    public function isOrderStatusDropdownDisabled()
    {
        return (bool) $this->scopeConfig->getValue(
            "omniful_core/general/disable_order_status_dropdown",
            ScopeInterface::SCOPE_STORE
        );
    }
    public function getWebhookUrl()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/webhook_url",
            ScopeInterface::SCOPE_STORE
        );
    }
    public function getWorkspaceId()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/workspace_id",
            ScopeInterface::SCOPE_STORE
        );
    }
    public function getAccessToken()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/access_token",
            ScopeInterface::SCOPE_STORE
        );
    }
    public function getWebhookToken()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/webhook_token",
            ScopeInterface::SCOPE_STORE
        );
    }

    public function getResponseStatus(
        $message,
        $code = null,
        $status = null,
        $data = null,
        $pageData = null,
        $nestedArray = true
    ) {
        if ($code == null) {
            $code = self::FAILED_HTTP_CODE;
        }
        if ($status == null) {
            $status = false;
        }

        $responseData["status"] = $this->getStatusResponse(
            $status,
            $code,
            $message
        );
        $responseData["data"] = [];
        if ($data) {
            $responseData["data"] = $data;
        }
        if ($pageData) {
            $responseData["pageData"] = $pageData;
        }

        if ($nestedArray) {
            $responseReturn[] = $responseData;
        } else {
            $responseReturn = $responseData;
        }

        return $responseReturn;
    }

    /**
     * getStatusResponse
     *
     * @param boolean $action
     * @param integer $httpCode
     * @param string $message
     * @return array
     */
    public function getStatusResponse(
        $action = true,
        $httpCode = self::ERROR_HTTP_CODE,
        $message = ""
    ): array {
        $statusResponse = [
            "httpCode" =>
                $httpCode == self::SUCCESS_HTTP_CODE
                    ? self::SUCCESS_HTTP_CODE
                    : ($httpCode == self::FAILED_HTTP_CODE
                        ? self::FAILED_HTTP_CODE
                        : $httpCode),
            "success" => $action ? true : false,
            "message" => $message
                ? __($message)->render()
                : __(self::EMPTY_CONTENT_CONTAINS)->render(),
        ];

        return $statusResponse;
    }
}
