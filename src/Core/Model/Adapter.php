<?php

namespace Omniful\Core\Model;

use Omniful\Core\Helper\Data;
use Magento\Framework\App\Request\Http;
use Omniful\Core\Logger\Logger;
use Magento\Framework\UrlInterface;

class Adapter
{
    /**
     * @var WebhookUrl
     */
    protected $webhookUrl;

    /**
     * @var Curl
     */
    protected $curl;

    /**
     * @var Data
     */
    protected $coreHelper;

    /**
     * @var Http
     */
    protected $request;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var string|null
     */
    protected $workspaceId;

    /**
     * @var string|null
     */
    protected $webhookToken;

    /**
     * @var Headers
     */
    protected $headers;
    /**
     * @var UrlInterface
     */
    protected $urlInterface;
    /**
     * @var Domain
     */
    protected $domain;
    /**
     * @var Timestamp
     */
    protected $timestamp;

    /**
     * Adapter constructor.
     *
     * @param Http                                $request
     * @param Logger                              $logger
     * @param Data                                $coreHelper
     * @param UrlInterface                        $urlInterface
     * @param \Magento\Framework\HTTP\Client\Curl $curl
     */
    public function __construct(
        Http $request,
        Logger $logger,
        Data $coreHelper,
        UrlInterface $urlInterface,
        \Magento\Framework\HTTP\Client\Curl $curl
    ) {
        $this->curl = $curl;
        $this->logger = $logger;
        $this->request = $request;
        $this->coreHelper = $coreHelper;
        $this->urlInterface = $urlInterface;
    }

    /**
     * Connect method
     *
     * @return void
     */
    public function connect()
    {
        $this->domain = null;
        $this->timestamp = null;
        $this->webhookUrl = null;
        $this->workspaceId = null;
        $this->webhookToken = null;

        $this->timestamp = date("Y-m-d H:i:s");
        $this->domain = $this->urlInterface->getBaseUrl();
        $userAgent = $this->request->getHeader("User-Agent");
        $xDeviceInfo = $this->request->getHeader("X-Device-Info");

        try {
            if (!$this->coreHelper->getIsActive()) {
                return;
            }

            $this->webhookUrl = $this->coreHelper->getWebhookUrl();
            $this->workspaceId = $this->coreHelper->getWorkspaceId();
            $this->webhookToken = $this->coreHelper->getWebhookToken();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            $this->logger->info($e->getMessage());
        }

        $this->headers = [
            "User-Agent" => $userAgent,
            "X-DOMAIN" => $this->domain,
            "X-Timestamp" => $this->timestamp,
            "Content-Type" => "application/json",
            "X-Device-Info" => $xDeviceInfo ?? "",
            "X-Webhook-Token" => $this->webhookToken,
            "X-Omniful-Merchant" => $this->workspaceId,
            "store_view_code" => "default",
        ];
    }

    /**
     * Cancel order method
     *
     * @param string $event
     * @param array $payload
     * @param array $additionalHeaders
     */
    public function publishMessage($event, $payload, $additionalHeaders = [])
    {
        $payload = [
            "event_name" => $event,
            "merchant_id" => $this->workspaceId,
            "timestamp" => $this->timestamp,
            "domain" => $this->domain,
            "data" => $payload,
        ];

        $this->logger->info("Payload: " . json_encode($payload));
        $this->headers["X-Event-Name"] = $event;

        $endPoint = $this->webhookUrl;

        $writer = new \Zend_Log_Writer_Stream(BP . '/var/log/headers.log');
        $logger = new \Zend_Log();
        $logger->addWriter($writer);
        $logger->info(print_r($additionalHeaders, true));
        $logger->info(print_r($this->headers, true));
        $logger->info(print_r(array_merge($this->headers, $additionalHeaders), true));

        $loggingData = [
            "endPoint" => $endPoint,
            "payload" => json_encode($payload),
            "headers" => array_merge($this->headers, $additionalHeaders),
        ];
        $this->headers = array_merge($this->headers, $additionalHeaders);

        $this->logger->info("LoggingData: " . json_encode($loggingData));
        $this->curl->setHeaders($this->headers);
        $response = $this->curl->post($endPoint, json_encode($payload));
        $this->logger->info("Response: " . json_encode($response));
        return $response;
    }
}
