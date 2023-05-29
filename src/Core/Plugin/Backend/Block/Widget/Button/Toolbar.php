<?php

namespace Omniful\Core\Plugin\Backend\Block\Widget\Button;

use Magento\Sales\Block\Adminhtml\Order\View;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;

use Omniful\Core\Helper\Data;

/**
 * Class Toolbar.
 */
class Toolbar
{
    protected $helper;

    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    public function beforePushButtons(
        ToolbarContext $toolbar,
        AbstractBlock $context,
        ButtonList $buttonList
    ) {
        if (!$context instanceof View) {
            return [$context, $buttonList];
        }
        if ($this->helper->isOrderShipButtonDisabled()) {
            $buttonList->remove("order_ship");
        }

        return [$context, $buttonList];
    }
}
