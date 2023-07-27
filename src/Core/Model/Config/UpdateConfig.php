<?php

namespace Omniful\Core\Model\Config;

use Magento\Framework\App\Cache\Type\Config;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Omniful\Core\Api\Config\UpdateConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Webapi\Rest\Request;
use Omniful\Core\Helper\Data;

class UpdateConfig implements UpdateConfigInterface
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
     * @var Data
     */
    private $helper;

    /**
     * UpdateConfig constructor.
     * @param WriterInterface $configWriter
     * @param Request $request
     * @param Data $helper
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        WriterInterface $configWriter,
        Request $request,
        Data $helper,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->cacheTypeList = $cacheTypeList;
        $this->helper = $helper;
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
            $path = 'omniful_core/general/';
            foreach ($params as $key => $value) {
                $this->configWriter
                    ->save($path . $key, $value, $scope = $store->getCode(), $store->getId());
            }
            if (isset($params['enable_debugging'])) {
                $this->configWriter->save(
                    'omniful_core/developer/enable_debugging',
                    $params['enable_debugging'],
                    $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    $scopeId = 0
                );
            }
            $this->cacheTypeList->cleanType(Config::TYPE_IDENTIFIER);
            return $this->helper->getResponseStatus(
                "Success",
                200,
                true,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (NoSuchEntityException $e) {
            return $this->helper->getResponseStatus(
                __(
                    "Config not found"
                ),
                404,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        } catch (Exception $e) {
            return $this->helper->getResponseStatus(
                __($e->getMessage()),
                500,
                false,
                $data = null,
                $pageData = null,
                $nestedArray = true
            );
        }
    }
}
