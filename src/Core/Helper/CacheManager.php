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
 */
class CacheManager
{
    public const DEFAULT = "default";
    public const WEBSITE_ID_CACHE_ID = "omniful_website_cache";
    public const STORE_ID_FROM_CACHE_ID = "omniful_store_id_cache_";
    public const CURRENCY_FROM_CACHE_ID = "omniful_currency_cache_";
    public const SCOPE_CONFIG_CACHE_ID = "omniful_scope_config_cache_";
    public const MEDIA_BASE_URL_CACHE_ID = "omniful_base_url_media_cache_";
    public const STOCK_SOURCE_CODE = "omniful_stock_source_code_";
    public const STORE_INFO_DETAILS = "omniful_store_info_details_";
    public const ALL_STORE_INFO = "omniful_all_store_info_";
    public const ORDER_STATUSES = "omniful_order_statuses_";
    public const CONFIG_DATA = "omniful_config_data_";
    public const PRODUCT_DATA = "omniful_product_data_";

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
     * @var CashableData
     */
    protected $cashableData;

    /**
     * @var StoreId
     */
    protected $storeId;

    /**
     * CacheManager constructor.
     *
     * @param CacheInterface        $cacheManager
     * @param SerializerInterface   $serializer
     * @param ScopeConfigInterface  $scopeConfig
     * @param StoreManagerInterface $storeManager
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
     * @param string $cacheIdentifier Cache ID
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
     * @param string $cacheIdentifier
     * @param string $savableData
     */
    public function saveDataToCache(string $cacheIdentifier, $savableData)
    {
        $cacheData = $this->serializer->serialize($savableData);
        $this->cacheManager->save($cacheData, $cacheIdentifier);
    }
}
