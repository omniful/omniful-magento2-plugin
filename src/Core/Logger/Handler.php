<?php

namespace Omniful\Core\Logger;

/**
 * Handler class.
 */
class Handler extends \Magento\Framework\Logger\Handler\Base
{
    /**
     * Logging level
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * File name
     * @var string
     */
    protected $fileName = "/var/log/omniful-log.log";
}
