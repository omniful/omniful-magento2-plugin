<?php
namespace Omniful\Core\Block\Adminhtml\View;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Registry;
use Magento\Sales\Helper\Admin;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Shipping\Model\CarrierFactory;
use Magento\Tax\Helper\Data as TaxHelper;

class Form extends \Magento\Shipping\Block\Adminhtml\View\Form
{
    /**
     * @var ProductMetadataInterface
     */
    public $productMetadata;
    /**
     * Form constructor.
     *
     * @param Context $context
     * @param Registry $registry
     * @param Admin $adminHelper
     * @param CarrierFactory $carrierFactory
     * @param ProductMetadataInterface $productMetadata
     * @param array $data
     * @param ShippingHelper|null $shippingHelper
     * @param TaxHelper|null $taxHelper
     */
    public function __construct(
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        CarrierFactory $carrierFactory,
        ProductMetadataInterface $productMetadata,
        array $data = [],
        ShippingHelper $shippingHelper = null,
        TaxHelper $taxHelper = null
    ) {
        $data["shippingHelper"] =
            $shippingHelper ??
            ObjectManager::getInstance()->get(ShippingHelper::class);
        $data["taxHelper"] =
            $taxHelper ?? ObjectManager::getInstance()->get(TaxHelper::class);
        $this->productMetadata = $productMetadata;
        parent::__construct($context, $registry, $adminHelper, $carrierFactory, $data, $shippingHelper, $taxHelper);
    }

    /**
     * Get Magento Version
     *
     * @return string
     */
    public function getMagentoVersion()
    {
        return $this->productMetadata->getVersion();
    }
}
