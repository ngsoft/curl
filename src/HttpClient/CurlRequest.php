<?php

namespace HttpClient;

use CurlHandler;

/**
 * @property-read ?resource $file
 * @property-read string $uid
 * @property-read int $requestCount
 */
class CurlRequest
{

    /** @var \CurlHandle|resource */
    protected $handle;
    protected $closed = true;
    protected $ready = false;
    /** @var string */
    protected $uid;

    protected $options = [];

    /** @var ?resource */
    protected $file = null;
    protected $initialCount = 0;

    protected $requestHeaders = [];
    protected $requestCount = 0;


    protected $parseHeaders = false;
    protected $rawHeaders = "";
    protected $responseHeaders = [];

    /** @var null|CurlResponse */
    protected $previous = null;

    /**
     * @param string|\Stringable $method
     * @param string|\Stringable $url
     * @param string|array|null $params
     * @return CurlResponse
     */

    public function fetch($method, $url, $params = null)
    {
        return $this
            ->prepare($method, $url, $params)
            ->execute();

    }


    /**
     * Make a GET request
     * @param string $url
     * @param null|string|array $params
     * @param ?array $headers
     * @return CurlResponse
     */
    public function get($url, $params = null, $headers = null)
    {
        if (is_array($headers)) {
            $this->setHeaders($headers);
        }
        return $this->fetch(self::GET, $url, $params);
    }


    /**
     * Make a POST request
     * if params are json please set header: "content-type" => "application/json"
     * @param string $url
     * @param null|string|array $params
     * @param ?array $headers
     * @return CurlResponse
     */
    public function post($url, $params = null, $headers = null)
    {
        if (is_array($headers)) {
            $this->setHeaders($headers);
        }

        return $this->fetch(self::POST, $url, $params);

    }


    /**
     * @param string|\Stringable $method
     * @param string|\Stringable $url
     * @param string|array|null $params
     * @return static
     */
    public function prepare($method, $url, $params = null)
    {

        $url = (string)$url;
        $method = (string)$method;

        $this->responseHeaders = [];
        $this->rawHeaders = "";
        $this->initialCount = $this->requestCount;


        $json = false;
        $requestMethod = strtoupper($method);
        if (preg_match("#^(.+)JSON$#", $requestMethod, $matches)) {
            $requestMethod = $matches[1];
            $json = true;
        }

        if (!CurlHandler::isValidMethod($requestMethod)) {
            throw new \InvalidArgumentException("Invalid method $requestMethod");
        }

        // for faster requests
        $this->unsetOpt(CURLOPT_HEADERFUNCTION);
        if ($this->parseHeaders) {
            $this->setOpt(CURLOPT_HEADERFUNCTION, $this->generateHeaderFunction());
        }

        $this->setOpt(CURLOPT_CUSTOMREQUEST, $requestMethod);

        if ($json && !$this->getHeader("content-type")) {
            $this->addHeader("content-type", "application/json");
        } elseif ($requestMethod !== "GET") {
            $this->addHeader("content-type", "application/x-www-form-urlencoded");
        }

        if (is_array($params)) {
            $params = $json ? json_encode($params) : http_build_query($params);
        }


        $this->unsetOpt(CURLOPT_POSTFIELDS);

        if ($method === "GET" && !$json) {
            $this->unsetOpt(CURLOPT_CUSTOMREQUEST);
            if (!empty($params)) {
                $url .= false !== strpos($url, "?") ? "&" : "?";
                $url .= $params;
            }
        } elseif (is_string($params)) {
            $this->setOpt(CURLOPT_POSTFIELDS, $params);
        }

        $this->unsetOpt(CURLOPT_HTTPHEADER);


        if (!empty($this->requestHeaders)) {
            $this->setOpt(CURLOPT_HTTPHEADER, $this->makeHeaders());
        }

        $this->setOpt(CURLOPT_URL, $url);
        $this->setOpt(CURLOPT_FILE, $this->createFileHandle());
        $ch = $this->getHandle();
        curl_reset($ch);
        foreach ($this->options as $name => $value) {
            curl_setopt($this->getHandle(), $name, $value);
        }
        $this->ready = true;
        return $this;
    }

    /**
     * @return CurlResponse
     */
    public function getResult()
    {

        $prev = &$this->previous;
        $ch = $this->getHandle();
        $info = curl_getinfo($ch);
        $statusCode = intval($info['http_code']);
        $success = 0 !== $statusCode;
        $statusText = CurlHandler::getReasonPhrase($statusCode);
        $info["status"] = $statusCode;
        $info["statusText"] = $statusText;
        $info["error"] = [
            curl_errno($ch) => curl_error($ch)
        ];


        $resp = CurlResponse::make([
            "success" => $success,
            "info" => $info,
            "stream" => $this->file,
            "headers" => $this->responseHeaders,
            "previous" => $prev,
            "redirections" => ($this->requestCount - $this->initialCount) - 1
        ]);

        // prevent infinite loop in execute on multi redirects
        $this->ready = false;

        // auto redirect (301,302)
        if (!empty($info["redirect_url"])) {
            $prev = $resp;
            // reset data for new request
            $this->rawHeaders = "";
            $this->responseHeaders = [];
            curl_setopt($ch, CURLOPT_FILE, $this->file = @fopen("php://temp", "r+"));
            curl_setopt($ch, CURLOPT_URL, $info["redirect_url"]);
            $this->ready = true;
        }
        return $resp;
    }


    /**
     * @return CurlResponse|null
     */
    public function execute()
    {
        if ($this->ready) {
            $this->previous = null;

            $ch = $this->getHandle();
            while (1) {
                @set_time_limit(120);
                @curl_exec($ch);

                $resp = $this->getResult();
                // redirection
                if ($this->ready) {
                    continue;
                }
                return $resp;
            }
        }
        return null;
    }


    public function __construct()
    {
        $this->uid = generate_uid();
        $this->options = [
            CURLOPT_ENCODING => 'gzip,deflate',
            CURLOPT_AUTOREFERER => true,
            CURLOPT_SSL_VERIFYPEER => 0,
        ];

    }


    public function __destruct()
    {
        if (!$this->closed) {
            @curl_close($this->handle);
        }

    }

    /**
     * @return \CurlHandle|resource
     */
    public function getHandle()
    {
        if ($this->closed) {
            $this->handle = curl_init();
            $this->closed = false;
        }
        return $this->handle;
    }

    /**
     * @return static
     */
    public function closeHandle()
    {
        if (!$this->closed) {
            @curl_close($this->handle);
            $this->closed = true;
            $this->ready = false;
            $this->uid = generate_uid();
            $this->handle = null;
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getUid()
    {
        return $this->uid;
    }

    /**
     * @return int
     */
    public function getRequestCount()
    {
        return $this->requestCount;
    }


    /**
     * @return bool
     */
    public function isReady()
    {
        return $this->ready;
    }


    /**
     * @param int $option
     * @param mixed $value
     * @return static
     */
    public function setOpt($option, $value)
    {
        $this->options[$option] = $value;
        return $this;

    }

    /**
     * @param array<int,mixed> $options
     * @return static
     */
    public function setOpts(array $options)
    {
        foreach ($options as $option => $value) {
            $this->setOpt($option, $value);
        }
        return $this;
    }

    /**
     * @param int $option
     * @return static
     */
    public function unsetOpt($option)
    {
        unset($this->options[$option]);
        return $this;
    }


    /**
     * @param string $file
     * @return static
     */
    public function setCookieFile($file)
    {
        $umask = @umask(0);
        @mkdir(dirname($file), true, 0777);
        @umask($umask);
        if (!is_writable(dirname($file))) {
            throw new \RuntimeException("Cookie file $file cannot be created.");
        }
        $this->setOpt(CURLOPT_COOKIEFILE, $file);
        return $this->setOpt(CURLOPT_COOKIEJAR, $file);
    }

    /**
     * @param int $timeout
     * @return static
     */
    public function setTimeout($timeout)
    {

        if (is_int($timeout) && $timeout > 0) {
            $this->setOpts([
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
            ]);
        }

        return $this;
    }


    /**
     * @param string|bool|null $userAgent
     * @return static
     */
    public function setUserAgent($userAgent = null)
    {
        if (is_int($userAgent) || true === $userAgent || null === $userAgent) {
            $userAgent = CurlHandler::generateUserAgent($userAgent);
        }

        unset($this->requestHeaders["user-agent"]);

        if (false === $userAgent) {
            unset($this->options[CURLOPT_USERAGENT]);
            return $this;
        }

        return $this->setOpt(CURLOPT_USERAGENT, $userAgent);

    }

    /**
     * @return bool
     */
    public function canParseHeaders()
    {
        return $this->parseHeaders;
    }

    /**
     * @param bool $parseHeaders
     * @return static
     */
    public function enableHeaderParsing($parseHeaders = true)
    {
        $this->parseHeaders = $parseHeaders !== false;
        return $this;
    }


    /**
     * @param string $name
     * @return string
     */
    public function getHeader($name)
    {
        if (!isset($this->requestHeaders[strtolower($name)])) {
            return "";
        }
        return $this->requestHeaders[strtolower($name)];
    }


    /**
     * Erases previous headers and replaces them with provided values
     * @param array<string,string|string[]> $headers
     * @return static
     */
    public function setHeaders(array $headers)
    {
        $this->requestHeaders = [];
        return $this->addHeaders($headers);
    }


    /**
     * @param array<string,string|string[]> $headers
     * @return static
     */
    public function addHeaders(array $headers)
    {
        foreach ($headers as $name => $value) {
            $this->addHeader($name, $value);
        }
        return $this;
    }

    /**
     * @param string $name
     * @param string|string[] $value
     * @return $this
     */
    public function addHeader($name, $value)
    {

        if (!is_array($value)) {
            $value = array_slice(func_get_args(), 1);
        }
        if (is_string($name)) {
            $name = strtolower($name);
            if ($name === "user-agent") {
                return $this->setOpt(CURLOPT_USERAGENT, $value[0]);
            }
            $this->requestHeaders[$name] = implode(", ", $value);

            if ($name === "referer") {
                unset($this->options[CURLOPT_AUTOREFERER]);
            }

        }
        return $this;
    }

    public function removeHeader($name)
    {
        unset($this->requestHeaders[strtolower($name)]);
        return $this;
    }


    /**
     * @param string $name
     * @return string
     */
    protected function getHeaderName($name)
    {
        return ucfirst(preg_replace_callback('#-([a-z])#', function ($matches) {
            return strtoupper($matches[1]);
        }, strtolower($name)));
    }

    /**
     * @return string[]
     */
    protected function makeHeaders()
    {
        $headers = [];
        foreach ($this->requestHeaders as $name => $value) {
            $headers[] = sprintf('%s: %s', $this->getHeaderName($name), $value);
        }
        return $headers;
    }

    /**
     * @return resource
     */
    protected function createFileHandle()
    {
        if ($this->file) {
            @fclose($this->file);
        }
        return $this->file = @fopen("php://temp", "r+");
    }


    protected function generateHeaderFunction()
    {
        return function () {
            $doNotSplit = ["set-cookie"];
            $this->rawHeaders .= $raw = func_get_arg(1);
            $len = strlen($raw);

            if (!empty($line = rtrim($raw)) && preg_match("#^(\H+):\h+(.+)$#", $line, $matches)) {


                $responseHeaders = &$this->responseHeaders;
                list(, $name, $values) = $matches;
                $name = strtolower($name);
                if (!isset($responseHeaders[$name])) {
                    $responseHeaders[$name] = [];
                }

                // dates and others
                if (in_array($name, $doNotSplit) || false !== strtotime($values)) {
                    $responseHeaders[$name][] = trim($values);
                    return $len;
                }


                foreach (explode(",", $values) as $value) {
                    $responseHeaders[$name][] = trim($value);
                }

            } elseif (0 === strpos($raw, "HTTP/")) {
                // detects a new request
                $this->responseHeaders = [];
                $this->rawHeaders = $raw;
                $this->requestCount++;
            }

            return $len;
        };

    }

    public function __get($name)
    {
        if (!$this->__isset($name)) {
            return null;
        }
        return $this->{$name};
    }


    public function __isset($name)
    {
        return property_exists($this, $name) && $this->{$name} !== null;
    }


    public function __set($name, $value)
    {

    }

    public function __unset($name)
    {
    }


    /**
     * Methods
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Methods
     */
    const GET = "GET";
    const HEAD = "HEAD";
    const POST = "POST";
    const PUT = "PUT";
    const DELETE = "DELETE";
    const CONNECT = "CONNECT";
    const OPTIONS = "OPTIONS";
    const TRACE = "TRACE";
    const PATCH = "PATCH";

}