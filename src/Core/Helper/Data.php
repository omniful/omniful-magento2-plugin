<?php

namespace Omniful\Core\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data extends AbstractHelper
{
    public const XML_PATH_ENABLE_MODULE = "omniful_core/general/active";
    public const XML_PATH_DISABLE_SHIP_BUTTON = "omniful_core/general/disable_ship_button";
    public const XML_PATH_DISABLE_ORDER_STATUS_DROPDOWN = "omniful_core/general/disable_order_status_dropdown";
    public const XML_PATH_WEB_HOOK_URL = "omniful_core/general/webhook_url";
    public const XML_PATH_WORK_SPACE_ID = "omniful_core/general/workspace_id";
    public const XML_PATH_WEB_HOOK_TOKEN = "omniful_core/general/webhook_token";

    public const SUCCESS_HTTP_CODE = 200;
    public const FAILED_HTTP_CODE = 204;
    public const ERROR_HTTP_CODE = 500;
    public const EMPTY_CONTENT_CONTAINS = "No Data are available.";
    /**
     * @var StoreManagerInterface
     */
    public $storeManager;
    /**
     * @var CollectionFactory
     */
    public $statusCollectionFactory;
    /**
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;
    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * Info constructor.
     *
     * @param StoreManagerInterface $storeManager
     * @param CollectionFactory $statusCollectionFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        CollectionFactory $statusCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        UrlInterface $urlBuilder
    ) {
        $this->storeManager = $storeManager;
        $this->statusCollectionFactory = $statusCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Get Is Active
     *
     * @param mixed $storeId
     * @return bool
     */
    public function getIsActive($storeId = null)
    {
        if ($storeId) {
            $this->storeManager->setCurrentStore($storeId);
        }
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(
            self::XML_PATH_ENABLE_MODULE,
            $storeScope
        );
    }

    /**
     * Is Order Ship Button Disabled
     *
     * @return bool
     */
    public function isOrderShipButtonDisabled()
    {
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISABLE_SHIP_BUTTON,
            $storeScope
        );
    }

    /**
     * Is Order Status Dropdown Disabled
     *
     * @return bool
     */
    public function isOrderStatusDropdownDisabled()
    {
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(
            self::XML_PATH_DISABLE_ORDER_STATUS_DROPDOWN,
            $storeScope
        );
    }

    /**
     * Get Webhook Url
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getWebhookUrl($storeId = null)
    {
        if ($storeId) {
            $this->storeManager->setCurrentStore($storeId);
        }
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(
            self::XML_PATH_WEB_HOOK_URL,
            $storeScope
        );
    }

    /**
     * Get Workspace Id
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getWorkspaceId($storeId = null)
    {
        if ($storeId) {
            $this->storeManager->setCurrentStore($storeId);
        }
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(
            self::XML_PATH_WORK_SPACE_ID,
            $storeScope
        );
    }

    /**
     * Get Webhook Token
     *
     * @param mixed $storeId
     * @return mixed
     */
    public function getWebhookToken($storeId = null)
    {
        if ($storeId) {
            $this->storeManager->setCurrentStore($storeId);
        }
        $storeScope = ScopeInterface::SCOPE_STORE;
        return $this->scopeConfig->getValue(
            self::XML_PATH_WEB_HOOK_TOKEN,
            $storeScope
        );
    }

    /**
     * GetResponseStatus
     *
     * @param mixed $message
     * @param mixed $code
     * @param mixed $status
     * @param mixed $data
     * @param mixed $pageData
     * @param bool $nestedArray
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
        $contentMessage = self::EMPTY_CONTENT_CONTAINS;
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
                : __($contentMessage)->render(),
        ];

        return $statusResponse;
    }

    /**
     * Get allowed countries for shipping
     *
     * @return array|null
     */
    public function getAllowedCountries(): ?array
    {
        return explode(",", $this->getConfigValue("general/country/allow"));
    }

    /**
     * Get configuration value by path
     *
     * @param string $path
     * @return mixed|null
     */
    public function getConfigValue(string $path)
    {
        return $this->scopeConfig->getValue($path, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Get store URLs
     *
     * @return array
     */
    public function getStoreUrls(): array
    {
        $storeUrls = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeUrls[] = [
                "store_id" => $store->getId(),
                "store_code" => $store->getCode(),
                "store_url" => $store->getBaseUrl(),
                "store_secure_url" => $store->getBaseUrl(
                    UrlInterface::URL_TYPE_WEB,
                    true
                ),
                "store_frontend_url" => $store->getBaseUrl(
                    UrlInterface::URL_TYPE_LINK
                ),
                "store_admin_url" =>
                    $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true) .
                    "admin/",
            ];
        }
        return $storeUrls;
    }
}
