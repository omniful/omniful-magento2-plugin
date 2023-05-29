<?php

declare(strict_types=1);

namespace Omniful\Core\Plugin\Framework\HTTP\Client;

use Omniful\Core\Helper\Data;

/**
 * Class to work with HTTP protocol using curl library
 *
 */
class Curl extends \Magento\Framework\HTTP\Client\Curl implements
    \Magento\Framework\HTTP\ClientInterface
{
    /**
     * Max supported protocol by curl CURL_SSLVERSION_TLSv1_2
     * @var int
     */
    private $sslVersion;

    /**
     * Hostname
     * @var string
     */
    protected $_host = "localhost";

    /**
     * @var int
     */
    protected $_port = 80;

    /**
     * Stream resource
     * @var object
     */
    protected $_sock = null;

    /**
     * Request headers
     * @var array
     */
    protected $_headers = [];

    /**
     * Fields for POST method - hash
     * @var array
     */
    protected $_postFields = [];

    /**
     * Request cookies
     * @var array
     */
    protected $_cookies = [];

    /**
     * @var array
     */
    protected $_responseHeaders = [];

    /**
     * @var string
     */
    protected $_responseBody = "";

    /**
     * @var int
     */
    protected $_responseStatus = 0;

    /**
     * Request timeout
     * @var int type
     */
    protected $_timeout = 300;

    /**
     * TODO
     * @var int
     */
    protected $_redirectCount = 0;

    /**
     * Curl
     * @var resource
     */
    protected $_ch;

    /**
     * Data
     * @var resource
     */
    protected $helper;

    /**
     * User overrides options hash
     * Are applied before curl_exec
     *
     * @var array
     */
    protected $_curlUserOptions = [];

    /**
     * Header count, used while parsing headers
     * in CURL callback function
     * @var int
     */
    protected $_headerCount = 0;

    /**
     * Set request timeout
     *
     * @param int $value value in seconds
     * @return void
     */
    public function setTimeout($value)
    {
        $this->_timeout = (int) $value;
    }

    /**
     * @param int|null $sslVersion
     */
    public function __construct(Data $helper, $sslVersion = null)
    {
        $this->sslVersion = $sslVersion;
        $this->helper = $helper;
    }

    /**
     * Set headers from hash
     *
     * @param array $headers
     * @return void
     */
    public function setHeaders($headers)
    {
        $this->_headers = $headers;
    }

    /**
     * Add header
     *
     * @param string $name name, ex. "Location"
     * @param string $value value ex. "http://google.com"
     * @return void
     */
    public function addHeader($name, $value)
    {
        $this->_headers[$name] = $value;
    }

    /**
     * Remove specified header
     *
     * @param string $name
     * @return void
     */
    public function removeHeader($name)
    {
        unset($this->_headers[$name]);
    }

    /**
     * Authorization: Basic header
     *
     * Login credentials support
     *
     * @param string $login username
     * @param string $pass password
     * @return void
     */
    public function setCredentials($login, $pass)
    {
        $val = base64_encode("{$login}:{$pass}");
        $this->addHeader("Authorization", "Basic {$val}");
    }

    /**
     * Add cookie
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function addCookie($name, $value)
    {
        $this->_cookies[$name] = $value;
    }

    /**
     * Remove cookie
     *
     * @param string $name
     * @return void
     */
    public function removeCookie($name)
    {
        unset($this->_cookies[$name]);
    }

    /**
     * Set cookies array
     *
     * @param array $cookies
     * @return void
     */
    public function setCookies($cookies)
    {
        $this->_cookies = $cookies;
    }

    /**
     * Clear cookies
     *
     * @return void
     */
    public function removeCookies()
    {
        $this->setCookies([]);
    }

    /**
     * Make GET request
     *
     * @param string $uri uri relative to host, ex. "/index.php"
     * @return void
     */
    public function get($uri)
    {
        $this->makeRequest("GET", $uri);
    }

    /**
     * Make POST request
     *
     * String type was added to parameter $param in order to support sending JSON or XML requests.
     * This feature was added base on Community Pull Request https://github.com/magento/magento2/pull/8373
     *
     * @param string $uri
     * @param array|string $params
     * @return void
     *
     * @see \Magento\Framework\HTTP\Client#post($uri, $params)
     */
    public function post($uri, $params)
    {
        $this->makeRequest("POST", $uri, $params);
    }

    /**
     * Make GET request
     *
     * @param string $uri uri relative to host, ex. "/index.php"
     * @return void
     */
    public function _get($uri)
    {
        return $this->_makeRequest("GET", $uri);
    }

    /**
     * Make POST request
     *
     * String type was added to parameter $param in order to support sending JSON or XML requests.
     * This feature was added base on Community Pull Request https://github.com/magento/magento2/pull/8373
     *
     * @param string $uri
     * @param array|string $params
     * @return void
     *
     * @see \Magento\Framework\HTTP\Client#post($uri, $params)
     */
    public function _post($uri, $params)
    {
        return $this->_makeRequest("POST", $uri, $params);
    }

    public function _put($uri, $params)
    {
        return $this->_makeRequest("PUT", $uri, $params);
    }

    /**
     * Make POST request
     *
     * String type was added to parameter $param in order to support sending JSON or XML requests.
     * This feature was added base on Community Pull Request https://github.com/magento/magento2/pull/8373
     *
     * @param string $uri
     * @param array|string $params
     * @return void
     *
     * @see \Magento\Framework\HTTP\Client#post($uri, $params)
     */
    public function _patch($uri, $params)
    {
        return $this->_makeRequest("PATCH", $uri, $params);
    }

    /**
     * Make POST request
     *
     * String type was added to parameter $param in order to support sending JSON or XML requests.
     * This feature was added base on Community Pull Request https://github.com/magento/magento2/pull/8373
     *
     * @param string $uri
     * @param array|string $params
     * @return void
     *
     * @see \Magento\Framework\HTTP\Client#post($uri, $params)
     */
    public function _delete($uri, $params = [])
    {
        return $this->_makeRequest("DELETE", $uri, $params);
    }

    /**
     * Get response headers
     *
     * @return array
     */
    public function getHeaders()
    {
        return $this->_responseHeaders;
    }

    /**
     * Get response body
     *
     * @return string
     */
    public function getBody()
    {
        return $this->_responseBody;
    }

    /**
     * Get cookies response hash
     *
     * @return array
     */
    public function getCookies()
    {
        if (empty($this->_responseHeaders["Set-Cookie"])) {
            return [];
        }
        $out = [];
        foreach ($this->_responseHeaders["Set-Cookie"] as $row) {
            $values = explode("; ", $row ?? "");
            $c = count($values);
            if (!$c) {
                continue;
            }
            list($key, $val) = explode("=", $values[0]);
            if ($val === null) {
                continue;
            }
            $out[trim($key)] = trim($val);
        }
        return $out;
    }

    /**
     * Get cookies array with details
     * (domain, expire time etc)
     *
     * @return array
     */
    public function getCookiesFull()
    {
        if (empty($this->_responseHeaders["Set-Cookie"])) {
            return [];
        }
        $out = [];
        foreach ($this->_responseHeaders["Set-Cookie"] as $row) {
            $values = explode("; ", $row ?? "");
            $c = count($values);
            if (!$c) {
                continue;
            }
            list($key, $val) = explode("=", $values[0]);
            if ($val === null) {
                continue;
            }
            $out[trim($key)] = ["value" => trim($val)];
            array_shift($values);
            $c--;
            if (!$c) {
                continue;
            }
            for ($i = 0; $i < $c; $i++) {
                list($subkey, $val) = explode("=", $values[$i]);
                $out[trim($key)][trim($subkey)] =
                    $val !== null ? trim($val) : "";
            }
        }
        return $out;
    }

    /**
     * Get response status code
     *
     * @see lib\Magento\Framework\HTTP\Client#getStatus()
     *
     * @return int
     */
    public function getStatus()
    {
        return $this->_responseStatus;
    }

    /**
     * Make request
     *
     * String type was added to parameter $param in order to support sending JSON or XML requests.
     * This feature was added base on Community Pull Request https://github.com/magento/magento2/pull/8373
     *
     * @param string $method
     * @param string $uri
     * @param array|string $params - use $params as a string in case of JSON or XML POST request.
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    protected function makeRequest($method, $uri, $params = [])
    {
        $this->_ch = curl_init();
        $this->curlOption(
            CURLOPT_PROTOCOLS,
            CURLPROTO_HTTP | CURLPROTO_HTTPS | CURLPROTO_FTP | CURLPROTO_FTPS
        );
        $this->curlOption(CURLOPT_URL, $uri);
        if ($method == "POST") {
            $this->curlOption(CURLOPT_POST, 1);
            $this->curlOption(CURLOPT_POSTFIELDS, $params);
        } elseif ($method == "GET") {
            $this->curlOption(CURLOPT_HTTPGET, 1);
        } else {
            $this->curlOption(CURLOPT_CUSTOMREQUEST, $method);
            $this->curlOption(CURLOPT_POSTFIELDS, $params);
        }

        if (count($this->_headers)) {
            $heads = [];
            foreach ($this->_headers as $k => $v) {
                $heads[] = $k . ": " . $v;
            }
            $this->curlOption(CURLOPT_HTTPHEADER, $heads);
        }

        if (count($this->_cookies)) {
            $cookies = [];
            foreach ($this->_cookies as $k => $v) {
                $cookies[] = "{$k}={$v}";
            }
            $this->curlOption(CURLOPT_COOKIE, implode(";", $cookies));
        }

        if ($this->_timeout) {
            $this->curlOption(CURLOPT_TIMEOUT, $this->_timeout);
        }

        if ($this->_port != 80) {
            $this->curlOption(CURLOPT_PORT, $this->_port);
        }

        $this->curlOption(CURLOPT_RETURNTRANSFER, 1);
        $this->curlOption(CURLOPT_HEADERFUNCTION, [$this, "parseHeaders"]);
        if ($this->sslVersion !== null) {
            $this->curlOption(CURLOPT_SSLVERSION, $this->sslVersion);
        }

        if (count($this->_curlUserOptions)) {
            foreach ($this->_curlUserOptions as $k => $v) {
                $this->curlOption($k, $v);
            }
        }

        $this->_headerCount = 0;
        $this->_responseHeaders = [];
        $this->_responseBody = curl_exec($this->_ch);
        $err = curl_errno($this->_ch);

        if ($err) {
            $this->doError(curl_error($this->_ch));
        }

        /* GET CURL ERROR. */
        $this->curlError = curl_error($this->_ch);

        /* Check for 404 (file not found). */
        $this->httpCode = curl_getinfo($this->_ch, CURLINFO_HTTP_CODE);

        curl_close($this->_ch);
    }

    /**
     * Make request
     *
     * String type was added to parameter $param in order to support sending JSON or XML requests.
     * This feature was added base on Community Pull Request https://github.com/magento/magento2/pull/8373
     *
     * @param string $method
     * @param string $uri
     * @param array|string $params - use $params as a string in case of JSON or XML POST request.
     *
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function _makeRequest($method, $uri, $params = [])
    {
        $this->makeRequest($method, $uri, $params);

        /* GETTING RESPONSE. */
        $response = json_decode($this->getBody(), true);

        /* HANDLING RATE LIMIT */
        $this->handleRateLimit($this->getHeaders(), $this->httpCode);

        if ($response) {
            // Check the HTTP Status code
            switch ($this->httpCode) {
                case 201:
                    $http_message = "201: Success";
                    break;
                case 200:
                    $http_message = "200: Success";
                    break;
                case 404:
                    $http_message = "404: Not found";
                    break;
                case 403:
                    $http_message = "403: Scopes Issue";
                    break;
                case 204:
                    $http_message = "204: Content Deleted or Not found";
                    break;
                case 401:
                    $http_message = "401: API Auth Failed";
                    break;
                case 301:
                    $http_message = "403: API Endpoint Issue";
                    break;
                case 500:
                    $http_message = "500: servers replied with an error.";
                    break;
                case 502:
                    $http_message =
                        "502: servers may be down or being upgraded. Hopefully they'll be OK soon!";
                    break;
                case 503:
                    $http_message =
                        "503: service unavailable. Hopefully they'll be OK soon!";
                    break;
                case 429:
                    $http_message =
                        "429: Too many request. Hopefully they'll be OK soon!";
                    break;
                default:
                    $http_message =
                        "Undocumented error: " .
                        $this->httpCode .
                        " : " .
                        $this->curlError;
                    break;
            }

            if (isset($response["data"])) {
                $responseData = $response["data"];
            } elseif (
                isset($response["status"]) &&
                (isset($response["category"]) || isset($response["categories"]))
            ) {
                $responseData = $response;
            } elseif (isset($response)) {
                $responseData = $response;
            } else {
                $responseData = [];
            }

            $returnData = $this->helper->getResponseStatus(
                isset($response["error"]) && $response["error"]["message"]
                    ? $response["error"]["message"]
                    : $http_message,
                $this->httpCode == 200 || $this->httpCode == 201
                    ? 200
                    : $this->httpCode,
                $this->httpCode == 200 ||
                $this->httpCode == 201 ||
                $this->httpCode == 202
                    ? true
                    : false,
                $responseData,
                null,
                false
            );

            return $returnData;
        }
    }

    /**
     * Throw error exception
     *
     * @param string $string
     * @return void
     * @throws \Exception
     */
    public function doError($string)
    {
        //  phpcs:ignore Magento2.Exceptions.DirectThrow
        throw new \Exception($string);
    }

    /**
     * Parse headers - CURL callback function
     *
     * @param resource $ch curl handle, not needed
     * @param string $data
     * @return int
     * @throws \Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function parseHeaders($ch, $data)
    {
        $data = $data !== null ? $data : "";
        if ($this->_headerCount == 0) {
            $line = explode(" ", trim($data), 3);
            if (count($line) < 2) {
                $this->doError(
                    "Invalid response line returned from server: " . $data
                );
            }
            $this->_responseStatus = (int) $line[1];
        } else {
            $name = $value = "";
            $out = explode(": ", trim($data), 2);
            if (count($out) == 2) {
                $name = $out[0];
                $value = $out[1];
            }

            if (strlen($name)) {
                if ("set-cookie" === strtolower($name)) {
                    $this->_responseHeaders["Set-Cookie"][] = $value;
                } else {
                    $this->_responseHeaders[$name] = $value;
                }
            }
        }
        $this->_headerCount++;

        return strlen($data);
    }

    /**
     * Set curl option directly
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    protected function curlOption($name, $value)
    {
        curl_setopt($this->_ch, $name, $value);
    }

    /**
     * Set curl options array directly
     *
     * @param array $arr
     * @return void
     */
    protected function curlOptions($arr)
    {
        curl_setopt_array($this->_ch, $arr);
    }

    /**
     * Set CURL options overrides array
     *
     * @param array $arr
     * @return void
     */
    public function setOptions($arr)
    {
        $this->_curlUserOptions = $arr;
    }

    /**
     * Set curl option
     *
     * @param string $name
     * @param string $value
     * @return void
     */
    public function setOption($name, $value)
    {
        $this->_curlUserOptions[$name] = $value;
    }

    /**
     * HANDLING RATE LIMIT
     *
     * @param string $headers
     * @param string $value
     * @return void
     */
    public function handleRateLimit($headers, $httpCode)
    {
        if (
            isset($headers["x-ratelimit-limit"]) &&
            isset($headers["x-ratelimit-remaining"]) &&
            isset($headers["x-ratelimit-reset"])
        ) {
            $limit = (int) $headers["x-ratelimit-limit"];
            $remaining = (int) $headers["x-ratelimit-remaining"];
            $reset = (int) $headers["x-ratelimit-reset"];
            $retryAfter = isset($headers["retry-after"])
                ? (int) $headers["retry-after"]
                : 0;

            // check if sliding window rate limit is available
            if (isset($headers["x-ratelimit-window"])) {
                $windowSize = (int) $headers["x-ratelimit-window"];
                $windowStart = time() - $windowSize;

                // get the number of requests made within the sliding window
                $requestsMade = 0;
                if (isset($headers["x-ratelimit-window-requests"])) {
                    $requestsMade =
                        (int) $headers["x-ratelimit-window-requests"];
                }

                // calculate the number of requests remaining within the sliding window
                $remaining = $limit - $requestsMade;

                // if there are no requests remaining within the sliding window, calculate the time to wait until the window resets
                if ($remaining == 0) {
                    $timeUntilReset = $windowStart + $windowSize - time() + 1;
                    sleep($timeUntilReset);
                }
            }

            // if there are remaining requests, calculate the time to wait until the next request can be made
            if ($remaining > 0) {
                $waitTime = max(ceil($limit / $remaining), $retryAfter);
                sleep($waitTime);
            }
        } elseif (isset($httpCode) && $httpCode == 429) {
            sleep(30);
        }
    }
}
