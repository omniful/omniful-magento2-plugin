<?php

namespace Omniful\Core\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    public const SUCCESS_HTTP_CODE = 200;
    public const FAILED_HTTP_CODE = 204;
    public const ERROR_HTTP_CODE = 500;
    public const EMPTY_CONTENT_CONTAINS = "No Data are available.";

    /**
     * Get Is Active
     *
     * @return bool
     */
    public function getIsActive()
    {
        return (bool) $this->scopeConfig->getValue(
            "omniful_core/general/active",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is Order Ship Button Disabled
     *
     * @return bool
     */
    public function isOrderShipButtonDisabled()
    {
        return (bool) $this->scopeConfig->getValue(
            "omniful_core/general/disable_ship_button",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Is Order Status Dropdown Disabled
     *
     * @return bool
     */
    public function isOrderStatusDropdownDisabled()
    {
        return (bool) $this->scopeConfig->getValue(
            "omniful_core/general/disable_order_status_dropdown",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Webhook Url
     *
     * @return mixed
     */
    public function getWebhookUrl()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/webhook_url",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Workspace Id
     *
     * @return mixed
     */
    public function getWorkspaceId()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/workspace_id",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Access Token
     *
     * @return mixed
     */
    public function getAccessToken()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/access_token",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * Get Webhook Token
     *
     * @return mixed
     */
    public function getWebhookToken()
    {
        return $this->scopeConfig->getValue(
            "omniful_core/general/webhook_token",
            ScopeInterface::SCOPE_STORE
        );
    }

    /**
     * GetResponseStatus
     *
     * @param  mixed $message
     * @param  mixed $code
     * @param  mixed $status
     * @param  mixed $data
     * @param  mixed $pageData
     * @param  bool  $nestedArray
     * @return mixed
     */
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
     * Get Status Response
     *
     * @param  boolean $action
     * @param  integer $httpCode
     * @param  string  $message
     * @return array
     */
    public function getStatusResponse(
        $action = true,
        $httpCode = self::ERROR_HTTP_CODE,
        $message = ""
    ): array {
        $contentMessage = self::EMPTY_CONTENT_CONTAINS;
        $statusResponse = [
            "httpCode" =>
                $httpCode == self::SUCCESS_HTTP_CODE
                    ? self::SUCCESS_HTTP_CODE
                    : ($httpCode == self::FAILED_HTTP_CODE
                        ? self::FAILED_HTTP_CODE
                        : $httpCode),
            "success" => $action ? true : false,
            "message" => $message ? __($message)->render() : __($contentMessage)->render(),
        ];

        return $statusResponse;
    }
}
