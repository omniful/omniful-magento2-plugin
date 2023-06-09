<?php

namespace Omniful\Core\Model;

use Omniful\Core\Helper\Data;
use Magento\Framework\App\Request\Http;
use Omniful\Core\Logger\Logger;
use Magento\Framework\UrlInterface;

class Adapter
{
    /**
     * API endpoint
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
    protected $accessToken;

    /**
     * @var string|null
     */
    protected $workspaceId;

    /**
     * @var string|null
     */
    protected $webhookToken;

    /**
     * @var array
     */
    protected $headers;

    protected $urlInterface;
    protected $domain;
    protected $timestamp;

    /**
     * Adapter constructor.
     *
     * @param \Omniful\Core\Helper\Data $coreHelper
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
        $this->accessToken = null;
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
            $this->accessToken = $this->coreHelper->getAccessToken();
            $this->workspaceId = $this->coreHelper->getWorkspaceId();
            $this->webhookToken = $this->coreHelper->getWebhookToken();
        } catch (\Magento\Framework\Exception\LocalizedException $e) {
            // $this->logger->info($e->getMessage());
        }

        $this->headers = [
            "User-Agent" => $userAgent,
            "X-DOMAIN" => $this->domain,
            "X-Timestamp" => $this->timestamp,
            "Content-Type" => "application/json",
            "X-Device-Info" => $xDeviceInfo ?? "",
            "X-Webhook-Token" => $this->webhookToken,
            "X-Omniful-Merchant" => $this->workspaceId,
            "Authorization" => "Bearer " . $this->accessToken,
        ];
    }

    /**
     * Cancel order method
     *
     * @param string $event
     * @param array $payload
     *
     * @return mixed
     */
    public function publishMessage($event, $payload)
    {
        $payload = [
            "event_name" => $event,
            "merchant_id" => $this->workspaceId,
            "timestamp" => $this->timestamp,
            "domain" => $this->domain,
            "data" => $payload,
        ];

        $this->headers["X-Event-Name"] = $event;

        $endPoint = $this->webhookUrl;

        $loggingData = [
            "endPoint" => $endPoint,
            "payload" => json_encode($payload),
            "headers" => $this->headers,
        ];

        file_put_contents(BP . '/var/log/publishMessage.log', print_r($loggingData, true) . "\n", FILE_APPEND);

        $this->curl->setHeaders($this->headers);
        $response = $this->curl->_post($endPoint, json_encode($payload));

        return $response;
    }
}