# Omniful Magento 2 Plugin

The Omniful Magento 2 Plugin enables integration between Magento 2 and Omniful Core which is an Order Management System (OMS) and Warehouse Management System (WMS). The integration ensures a smooth order fulfillment process, inventory management, and shipment tracking.

## Requirements

- Magento 2.x
- Omniful Core Account (API credentials)

## Installation

1. Navigate to the Magento 2 root directory and run the following to install the module with Composer:

   ```bash
   composer require omniful/magento2-plugin:*

2. Enable the module by running the following commands:

```bash
php bin/magento module:enable Omniful_Core Omniful_Integration
php bin/magento setup:upgrade
php bin/magento cache:clean

Configure the Omniful settings in the Magento 2 admin panel:

Go to Stores > Configuration > Sales > Omniful. Enter the Omniful API credentials in the respective fields. Adjust other configuration settings as necessary. Click Save Config.

Usage
Once the module is installed and configured, the following features will be available through the Omniful Magento 2 Plugin:

Order Sync
The module automatically synchronizes orders between Magento 2 and Omniful Core. Any new order placed in Magento 2 will be pushed to Omniful Core for fulfillment. Order updates, such as shipping information and status updates, are also synchronized.

Inventory Sync
Inventory levels are automatically synchronized between Magento 2 and Omniful Core. Stock updates, such as product quantities and availability, are reflected in both systems.

Shipment Tracking
Omniful Core provides real-time shipment tracking information which is then displayed within Magento 2. This feature makes it easier for you to track the status of shipments and provide accurate tracking information to customers.
