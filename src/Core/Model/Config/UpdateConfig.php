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
     * UpdateConfig constructor.
     * @param WriterInterface $configWriter
     * @param Request $request
     * @param TypeListInterface $cacheTypeList
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        WriterInterface $configWriter,
        Request $request,
        TypeListInterface $cacheTypeList,
        StoreManagerInterface $storeManager
    ) {
        $this->request = $request;
        $this->configWriter = $configWriter;
        $this->storeManager = $storeManager;
        $this->cacheTypeList = $cacheTypeList;
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
            $path = 'omniful_core/general/';
            foreach ($params as $key => $value) {
                $this->configWriter
                    ->save($path . $key, $value, $scope = ScopeConfigInterface::SCOPE_TYPE_DEFAULT, $scopeId = 0);
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
            $responseData[] = [
                "httpCode" => 200,
                "status" => true,
                "message" => "Success",
            ];
            return $responseData;
        } catch (NoSuchEntityException $e) {
            $responseData[] = [
                "httpCode" => 404,
                "status" => false,
                "message" => "Config not Save",
            ];
            return $responseData;
        } catch (Exception $e) {
            $responseData[] = [
                "httpCode" => 500,
                "status" => false,
                "message" => $e->getMessage(),
            ];
            return $responseData;
        }
    }
}
