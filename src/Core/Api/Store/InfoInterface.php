<?php

namespace Omniful\Core\Api\Store;

/**
 * Interface InfoInterface
 */
interface InfoInterface
{
    /**
     * Retrieve all store information.
     *
     * @return array An associative array containing store information.
     * The array should have the following keys:
     *  - 'store_name' (string): The name of the store.
     *  - 'address' (string): The store address.
     *  - 'phone' (string): The store contact phone number.
     *  - 'email' (string): The store contact email address.
     *  - 'website' (string): The store website URL.
     *  - 'business_hours' (string): The store business hours.
     *  - 'social_media' (array): An associative array containing social media links.
     *    Example: ['facebook' => 'https://facebook.com/store', 'twitter' => 'https://twitter.com/store']
     * @return array
     */
    public function getStoreInfo();
}
