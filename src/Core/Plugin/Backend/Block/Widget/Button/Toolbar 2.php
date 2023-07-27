<?php

namespace Omniful\Core\Plugin\Backend\Block\Widget\Button;

use Magento\Backend\Block\Widget\Button\ButtonList;
use Magento\Backend\Block\Widget\Button\Toolbar as ToolbarContext;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Sales\Block\Adminhtml\Order\View;
use Omniful\Core\Helper\Data;

class Toolbar
{
    /**
     * @var Data
     */
    protected $helper;

    /**
     * Toolbar constructor.
     *
     * @param Data $helper
     */
    public function __construct(Data $helper)
    {
        $this->helper = $helper;
    }

    /**
     * Before Push Buttons
     *
     * @param  ToolbarContext $toolbar
     * @param  AbstractBlock  $context
     * @param  ButtonList     $buttonList
     * @return array
     */
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
