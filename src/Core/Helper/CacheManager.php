<?php

namespace Omniful\Core\Helper;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * CacheManager
 *
 * This class provides functionality for caching of frequently used data. It manages storing,
 * retrieving, and checking data in cache. It uses Magento CacheInterface to interact with the cache.
 * It also uses Magento SerializerInterface to serialize and unserialize data before caching.
 * It uses Magento StoreManagerInterface to manage different stores in the Magento application.
 * It also uses Magento ScopeConfigInterface to fetch the configuration value saving those into cache.
 *
 * @category  Omniful\Core\Helper
 * @package   Omniful\Core\Helper
 */
class CacheManager
{
    const default = "default";
    const WEBSITE_ID_CACHE_ID = "omniful_website_cache";
    const STORE_ID_FROM_CACHE_ID = "omniful_store_id_cache_";
    const CURRENCY_FROM_CACHE_ID = "omniful_currency_cache_";
    const SCOPE_CONFIG_CACHE_ID = "omniful_scope_config_cache_";
    const MEDIA_BASE_URL_CACHE_ID = "omniful_base_url_media_cache_";

    /**
     * Serializer instance
     *
     * @var SerializerInterface
     */
    protected $serializer;

    /**
     * Cache manager instance
     *
     * @var CacheInterface
     */
    protected $cacheManager;

    /**
     * Store manager instance
     *
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * ScopeConfig instance
     *
     * @var ScopeConfigInterface
     */
    protected $scopeConfig;

    /**
     * Cashable data
     *
     * @var mixed
     */
    protected $cashableData;

    /**
     * Store ID
     *
     * @var integer
     */
    protected $storeId;

    /**
     * Construct the CacheManager class
     *
     * @param CacheInterface         $cacheManager  Cache manager instance
     * @param SerializerInterface    $serializer   Serializer instance
     * @param ScopeConfigInterface   $scopeConfig  ScopeConfig instance
     * @param StoreManagerInterface $storeManager Store manager instance
     */
    public function __construct(
        CacheInterface $cacheManager,
        SerializerInterface $serializer,
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager
    ) {
        $this->serializer = $serializer;
        $this->scopeConfig = $scopeConfig;
        $this->cacheManager = $cacheManager;
        $this->storeManager = $storeManager;
    }

    /**
     * Check if data is available in cache
     *
     * @param  string $cacheIdentifier Cache ID
     * @return bool
     */
    public function isDataAvailableInCache(string $cacheIdentifier): bool
    {
        $cache = $this->cacheManager->load($cacheIdentifier);
        if ($cache) {
            $this->cashableData = $cache;
            return true;
        }

        return false;
    }

    /**
     * Get data from cache
     *
     * @param  string $cacheIdentifier Cache ID
     * @return mixed
     */
    public function getDataFromCache(string $cacheIdentifier)
    {
        if ($this->isDataAvailableInCache($cacheIdentifier)) {
            return $this->serializer->unserialize($this->cashableData);
        }

        return false;
    }

    /**
     * Save data to cache
     *
     * @param  string              $cacheIdentifier Cache ID
     * @param  array|string|integer|bool $savableData    Data to save
     * @return void
     */
    public function saveDataToCache(string $cacheIdentifier, $savableData)
    {
        $cacheData = $this->serializer->serialize($savableData);
        $this->cacheManager->save($cacheData, $cacheIdentifier);
    }

    /**
     * Get website ID from cache
     *
     * @param  integer $storeId Store ID
     * @return mixed
     */
    public function getWebsiteIdFromCache(int $storeId)
    {
        $cacheIdentifier = self::WEBSITE_ID_CACHE_ID . $storeId;
        if ($this->isDataAvailableInCache($cacheIdentifier)) {
            return $this->getDataFromCache($cacheIdentifier);
        } else {
            $websiteId = $this->storeManager
                ->getStore($storeId)
                ->getWebsiteId();
            $this->saveDataToCache($cacheIdentifier, $websiteId);

            return $websiteId;
        }
    }

    /**
     * Get config data from cache
     *
     * @param  string   $path     Path to config data
     * @param  integer  $storeId  Store ID
     * @param  string   $scope    Scope type
     * @return string
     */
    public function getConfigDataFromCache(
        string $path,
        $storeId = null,
        $scope = ScopeInterface::SCOPE_STORE
    ) {
        $cacheIdentifier =
            self::SCOPE_CONFIG_CACHE_ID . $path . "_" . self::default;
        if ($storeId) {
            $cacheIdentifier =
                self::SCOPE_CONFIG_CACHE_ID . $path . "_" . $storeId;
        }

        if ($this->isDataAvailableInCache($cacheIdentifier)) {
            return $this->getDataFromCache($cacheIdentifier);
        } else {
            $value = $this->scopeConfig->getValue($path, $scope, $storeId);
            $this->saveDataToCache($cacheIdentifier, $value);

            return $value;
        }
    }

    /**
     * Get base URL from cache
     *
     * @param  integer|null $storeId Store ID
     * @param  string|null  $path    Path to URL
     * @return mixed
     */
    public function getBaseUrlFromCache(
        int $storeId = null,
        string $path = null
    ) {
        if (!$storeId) {
            $storeId = $this->getStoreIdFromCache();
        }
        $cacheIdentifier = self::MEDIA_BASE_URL_CACHE_ID . $storeId;
        if ($this->isDataAvailableInCache($cacheIdentifier)) {
            return $this->getDataFromCache($cacheIdentifier);
        } else {
            if ($path) {
                $cacheIdentifier =
                    self::MEDIA_BASE_URL_CACHE_ID . $path . "_" . $storeId;
                $baseUrl = $this->storeManager
                    ->getStore($storeId)
                    ->getBaseUrl($path, true);
            } else {
                $baseUrl = $this->storeManager
                    ->getStore($storeId)
                    ->getBaseUrl();
            }
            $this->saveDataToCache($cacheIdentifier, $baseUrl);

            return $baseUrl;
        }
    }

    /**
     * Get store ID from cache
     *
     * @return int
     */
    public function getStoreIdFromCache()
    {
        $cacheIdentifier = self::STORE_ID_FROM_CACHE_ID . $this->storeId;
        if ($this->isDataAvailableInCache($cacheIdentifier)) {
            return $this->getDataFromCache($cacheIdentifier);
        } else {
            $storeId = $this->storeManager->getStore()->getId();
            $this->storeId = $storeId;
            $cacheIdentifier = self::STORE_ID_FROM_CACHE_ID . $this->storeId;
            $this->saveDataToCache($cacheIdentifier, $storeId);

            return $storeId;
        }
    }

    /**
     * Get current currency code from cache
     *
     * @param  integer $storeId Store ID
     * @return string
     */
    public function getCurrentCurrencyCodeFromCache(int $storeId): string
    {
        $cacheIdentifier = self::CURRENCY_FROM_CACHE_ID . $storeId;
        if ($this->isDataAvailableInCache($cacheIdentifier)) {
            return $this->getDataFromCache($cacheIdentifier);
        } else {
            $currentCurrency = $this->storeManager
                ->getStore($storeId)
                ->getCurrentCurrencyCode();
            $this->saveDataToCache($cacheIdentifier, $currentCurrency);

            return $storeId;
        }
    }
}
