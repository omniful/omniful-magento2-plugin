<?php

namespace Omniful\Core\Model\Store;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;


class Data extends AbstractHelper
{

    public const SUCCESS_HTTP_CODE = 200;
    public const FAILED_HTTP_CODE = 204;
    public const ERROR_HTTP_CODE = 500;
    public const EMPTY_CONTENT_CONTAINS = "No Data are available.";


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
     * @return bool
     */
    public function getIsActive()
    {
        return (bool) $this->getConfigValue('omniful_core/general/active');
    }

    /**
     * Is Order Ship Button Disabled
     *
     * @return bool
     */
    public function isOrderShipButtonDisabled()
    {
        return (bool) $this->getConfigValue('omniful_core/general/disable_ship_button');
    }

    /**
     * Is Order Status Dropdown Disabled
     *
     * @return bool
     */
    public function isOrderStatusDropdownDisabled()
    {
        return (bool) $this->getConfigValue('omniful_core/general/disable_order_status_dropdown');
    }

    /**
     * Get Webhook Url
     *
     * @return mixed
     */
    public function getWebhookUrl()
    {
        return $this->getConfigValue('omniful_core/general/webhook_url');
    }

    /**
     * Get Workspace Id
     *
     * @return mixed
     */
    public function getWorkspaceId()
    {
        return $this->getConfigValue('omniful_core/general/workspace_id');
    }

    /**
     * Get Webhook Token
     *
     * @return mixed
     */
    public function getWebhookToken()
    {
        return $this->getConfigValue('omniful_core/general/webhook_token');
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

    /**
     * Get allowed countries for shipping
     *
     * @return array|null
     */
    private function getAllowedCountries(): ?array
    {
        return explode(',', $this->getConfigValue('general/country/allow'));
    }

    /**
     * Get store URLs
     *
     * @return array
     */
    private function getStoreUrls(): array
    {
        $storeUrls = [];
        $stores = $this->storeManager->getStores();
        foreach ($stores as $store) {
            $storeUrls[] = [
                'store_id' => $store->getId(),
                'store_code' => $store->getCode(),
                'store_url' => $store->getBaseUrl(),
                'store_secure_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true),
                'store_frontend_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_LINK),
                'store_admin_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_WEB, true) . 'admin/',
            ];
        }
        return $storeUrls;
    }

    /**
     * Get configuration value by path
     *
     * @param string $path
     * @return mixed|null
     */
    private function getConfigValue(string $path)
    {
        return $this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE
        );
    }
}