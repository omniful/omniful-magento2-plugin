<?php

namespace Omniful\Core\Model\Config;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Api\Config\ConfigurationsInterface;
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
     * UpdateConfig constructor.
     * @param Request $request
     * @param CoreHelper $coreHelper
     * @param WriterInterface $configWriter
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Request $request,
        CoreHelper $coreHelper,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->coreHelper = $coreHelper;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->cacheTypeList = $cacheTypeList;
    }

    /**
     * Get Omniful Configs
     *
     * @return mixed|void
     */
    public function getOmnifulConfigs()
    {
        try {
            $apiUrl = $this->request->getUriString();
            $index = strpos($apiUrl, "/rest/V2");
            if ($index !== false) {
                $storeId = 0;
            } else {
                $store = $this->storeManager->getStore();
                $storeId = $store->getId();
            }
            $configData = $this->getConfigData($storeId);
            return $this->coreHelper->getResponseStatus(
                __("Success"),
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
     * @param mixed $storeId
     * @return bool|mixed
     */
    public function getConfigData($storeId = null)
    {
        $configData["active"] = (bool)$this->coreHelper->getIsActive($storeId);
        $configData["webhook_url"] = $this->coreHelper->getWebhookUrl($storeId);
        $configData["workspace_id"] = $this->coreHelper->getWorkspaceId($storeId);
        $configData["webhook_token"] = $this->coreHelper->getWebhookToken($storeId);
        $configData["disable_ship_button"] = (bool)$this->coreHelper->isOrderShipButtonDisabled();
        $configData["disable_order_status_dropdown"] = (bool)$this->coreHelper->isOrderStatusDropdownDisabled();
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
            $apiUrl = $this->request->getUriString();
            $index = strpos($apiUrl, "/rest/V2");
            $scopeType = 'default';
            if ($index !== false) {
                $storeId = 0;
            } else {
                $store = $this->storeManager->getStore();
                $storeId = $store->getId();
                $scopeType = 'stores';
            }
            $path = "omniful_core/general/";
            foreach ($params as $key => $value) {
                $this->configWriter->save(
                    $path . $key,
                    $value,
                    $scopeType,
                    $storeId
                );
            }
            if (isset($params["enable_debugging"])) {
                $this->configWriter->save(
                    "omniful_core/developer/enable_debugging",
                    $params["enable_debugging"],
                    $scopeType,
                    $storeId
                );
            }
            $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);

            $configData = json_decode($this->request->getContent(), true);

            $configData['active'] = $configData['active'] ? true : false;
            $configData['disable_ship_button'] = $configData['disable_ship_button'] ? true :false;
            $configData['disable_order_status_dropdown'] = $configData['disable_order_status_dropdown'] ? true :false;
            $configData['enable_debugging'] = $configData['enable_debugging'] ? true :false;

            return $this->coreHelper->getResponseStatus(
                __("Success"),
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
}
