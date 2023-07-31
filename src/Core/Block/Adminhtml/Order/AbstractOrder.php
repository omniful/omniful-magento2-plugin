<?php

namespace Omniful\Core\Block\Adminhtml\Order;

use Magento\Sales\Model\Order;
use Magento\Framework\App\ObjectManager;
use Magento\Shipping\Helper\Data as ShippingHelper;
use Magento\Tax\Helper\Data as TaxHelper;

/**
 * Adminhtml order abstract block
 *
 * @api
 * @author Magento Core Team <core@magentocommerce.com>
 * @since  100.0.2
 */
class AbstractOrder extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    /**
     * AbstractOrder constructor.
     *
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Registry             $registry
     * @param \Magento\Sales\Helper\Admin             $adminHelper
     * @param array                                   $data
     * @param ShippingHelper|null                     $shippingHelper
     * @param TaxHelper|null                          $taxHelper
     */
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        array $data = [],
        ?ShippingHelper $shippingHelper = null,
        ?TaxHelper $taxHelper = null
    ) {
        $data["shippingHelper"] =
            $shippingHelper ??
            ObjectManager::getInstance()->get(ShippingHelper::class);
        $data["taxHelper"] =
            $taxHelper ?? ObjectManager::getInstance()->get(TaxHelper::class);
        parent::__construct($context, $registry, $adminHelper, $data);
    }
}
