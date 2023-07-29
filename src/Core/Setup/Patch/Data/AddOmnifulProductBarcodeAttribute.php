<?php

namespace Omniful\Core\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Validator\ValidateException;

class AddOmnifulProductBarcodeAttribute implements DataPatchInterface
{
    /**
     * ModuleDataSetupInterface.
     *
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * AddOmnifulProductBarcodeAttribute constructor.
     *
     * @param EavSetupFactory          $eavSetupFactory
     * @param ModuleDataSetupInterface $moduleDataSetup
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * Apply
     *
     * @return AddOmnifulProductBarcodeAttribute|void
     * @throws LocalizedException
     * @throws ValidateException
     */
    public function apply()
    {
        /**
         * @var EavSetup $eavSetup
         */
        $eavSetup = $this->eavSetupFactory->create([
            "setup" => $this->moduleDataSetup,
        ]);

        $eavSetup->addAttribute(
            \Magento\Catalog\Model\Product::ENTITY,
            "omniful_barcode_attribute",
            [
                "global" => 1,
                "system" => 1,
                "class" => "",
                "source" => "",
                "frontend" => "",
                "apply_to" => "",
                "unique" => true,
                "default" => null,
                "visible" => true,
                "input" => "text",
                "required" => false,
                "type" => "varchar",
                "group" => "General",
                "label" => "Barcode",
                "comparable" => true,
                "searchable" => true,
                "filterable" => false,
                "user_defined" => true,
                "visible_on_front" => true,
                "used_in_product_listing" => false,
            ]
        );
    }

    /**
     * Get Dependencies
     *
     * @return array|string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * Get Aliases
     *
     * @return array|string[]
     */
    public function getAliases()
    {
        return [];
    }
}
