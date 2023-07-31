<?php

namespace Omniful\Core\Model\Config;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Api\Config\ConfigurationsInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Webapi\Rest\Request;
use Omniful\Core\Helper\CacheManager as CacheManagerHelper;
use Omniful\Core\Helper\Data as CoreHelper;

class ConfigurationsManagement implements ConfigurationsInterface
{
    /**
     * @var WriterInterface
     */
    public $configWriter;

    /**
     * @var Request
     */
    public $request;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var TypeListInterface
     */
    private $cacheTypeList;

    /**
     * @var CoreHelper
     */
    private $coreHelper;
    /**
     * @var CacheManagerHelper
     */
    private $cacheManagerHelper;

    /**
     * UpdateConfig constructor.
     * @param Request $request
     * @param CoreHelper $coreHelper
     * @param WriterInterface $configWriter
     * @param CacheManagerHelper $cacheManagerHelper
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Request $request,
        CoreHelper $coreHelper,
        WriterInterface $configWriter,
        CacheManagerHelper $cacheManagerHelper,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->coreHelper = $coreHelper;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->cacheTypeList = $cacheTypeList;
        $this->cacheManagerHelper = $cacheManagerHelper;
    }

    /**
     * Get Omniful Configs
     *
     * @return mixed|void
     */
    public function getOmnifulConfigs()
    {
        try {
            $configData = $this->getConfigData();

            return $this->coreHelper->getResponseStatus(
                "Success",
                200,
                true,
                $configData
            );
        } catch (NoSuchEntityException $e) {
            return $this->coreHelper->getResponseStatus(
                __("Config not found"),
                404,
                false
            );
        } catch (Exception $e) {
            return $this->coreHelper->getResponseStatus(
                __($e->getMessage()),
                500,
                false
            );
        }
    }

    /**
     * Get Config Data
     *
     * @return mixed
     */
    public function getConfigData()
    {
        $storeId = $this->coreHelper->getStoreId();
        $cacheIdentifier = $this->cacheManagerHelper ::CONFIG_DATA.$storeId;
        if ($this->cacheManagerHelper->isDataAvailableInCache($cacheIdentifier)) {
            return $this->cacheManagerHelper->getDataFromCache($cacheIdentifier);
        }
        $configData["active"] = (bool)$this->coreHelper->getIsActive();
        $configData["webhook_url"] = $this->coreHelper->getWebhookUrl();
        $configData["workspace_id"] = $this->coreHelper->getWorkspaceId();
        $configData["webhook_token"] = $this->coreHelper->getWebhookToken();
        $configData["disable_ship_button"] = (bool)$this->coreHelper->isOrderShipButtonDisabled();
        $configData["disable_order_status_dropdown"] = (bool)$this->coreHelper->isOrderStatusDropdownDisabled();
        if ($cacheIdentifier) {
            $this->cacheManagerHelper->saveDataToCache($cacheIdentifier, $configData);
        }
        return $configData;
    }

    /**
     * UpdateConfig
     *
     * @return mixed|void
     */
    public function updateConfig()
    {
        try {
            $params = $this->request->getBodyParams();
            $store = $this->storeManager->getStore();
            $path = "omniful_core/general/";

            foreach ($params as $key => $value) {
                $this->configWriter->save(
                    $path . $key,
                    $value,
                    $scope = $store->getCode(),
                    $store->getId()
                );
            }

            if (isset($params["enable_debugging"])) {
                $this->configWriter->save(
                    "omniful_core/developer/enable_debugging",
                    $params["enable_debugging"],
                    $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    $scopeId = 0
                );
            }
            $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);

            $configData = $this->getConfigData();

            return $this->coreHelper->getResponseStatus(
                "Success",
                200,
                true,
                $configData
            );
        } catch (NoSuchEntityException $e) {
            return $this->coreHelper->getResponseStatus(
                __("Config not found"),
                404,
                false
            );
        } catch (Exception $e) {
            return $this->coreHelper->getResponseStatus(
                __($e->getMessage()),
                500,
                false
            );
        }
    }

    function getConfigData()
    {
        $configData["active"] = (bool) $this->coreHelper->getIsActive();
        $configData["webhook_url"] = $this->coreHelper->getWebhookUrl();
        $configData["workspace_id"] = $this->coreHelper->getWorkspaceId();
        $configData["webhook_token"] = $this->coreHelper->getWebhookToken();
        $configData[
            "disable_ship_button"
        ] = (bool) $this->coreHelper->isOrderShipButtonDisabled();
        $configData[
            "disable_order_status_dropdown"
        ] = (bool) $this->coreHelper->isOrderStatusDropdownDisabled();

        return $configData;
    }
}
