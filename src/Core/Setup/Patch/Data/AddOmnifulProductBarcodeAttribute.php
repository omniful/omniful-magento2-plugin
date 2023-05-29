<?php

namespace Omniful\Core\Setup\Patch\Data;

use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

class AddOmnifulProductBarcodeAttribute implements DataPatchInterface
{
    /**
     * ModuleDataSetupInterface.
     *
     * @var ModuleDataSetupInterface
     */
    private $moduleDataSetup;

    /**
     * EavSetupFactory.
     *
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * AddRecommendedAttribute constructor.
     */
    public function __construct(
        EavSetupFactory $eavSetupFactory,
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function apply()
    {
        /** @var EavSetup $eavSetup */
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
     * {@inheritdoc}
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases()
    {
        return [];
    }
}
