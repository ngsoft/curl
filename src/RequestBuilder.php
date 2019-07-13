<?php

declare(strict_types=1);

namespace NGSOFT\Curl;

use NGSOFT\Curl\Interfaces\CurlHelper;

class RequestBuilder {

    const CACERT_SRC = 'https://curl.haxx.se/ca/cacert.pem';

    /** Mozilla Firefox ESR 60 */
    const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:60.0) Gecko/20100101 Firefox/60.0";
    const CURL_DEFAULTS = [
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_ENCODING => "gzip,deflate",
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_AUTOREFERER => true,
        CURLINFO_HEADER_OUT => true,
        /** @link https://curl.haxx.se/libcurl/c/CURLOPT_COOKIEFILE.html Enables cookie engine without using a file */
        CURLOPT_COOKIEFILE => "",
    ];

    /** @var string|null */
    private static $cacertLocation;

    /** @var array<string,string> */
    private $headers = [];

    /** @var int */
    private $retry = 0;

    /** @var array<int,mixed> */
    private $opts = [];

    /** @var string|null */
    private $cookie;

    ////////////////////////////   UTILS   ////////////////////////////

    /**
     * Set the Certifications download folder
     * @param string $certlocation
     * @throws InvalidArgumentException
     */
    public static function setCertlocation(string $certlocation) {
        file_exists($certlocation) || @mkdir($certlocation, 0777, true);
        if (!is_dir($certlocation) or ! is_writable($certlocation)) {
            throw new RuntimeException("$certlocation is not an existing directory or is not writable.");
        }
        self::$certlocation = $certlocation . DIRECTORY_SEPARATOR . basename(self::CACERT_SRC);
    }

    /**
     * Downloads Certifications from haxx (if not already present)
     * @staticvar string $path
     * @return string|null
     */
    private function getCACert() {
        static $path = null;
        if ($path === null and self::$cacertLocation !== null) {
            $file = self::$cacertLocation;
            if (!is_file($file) and is_dir(dirname($file))) {
                $ch = curl_init();
                $this->curl_setopt_array($ch, self::CURL_DEFAULTS);
                $this->curl_setopt_array($ch, [
                    CURLOPT_URL => self::CACERT_SRC,
                    CURLOPT_SSL_VERIFYPEER => false
                ]);
                $contents = curl_exec($ch);
                $err = curl_errno($ch);
                curl_close($ch);
                if (!$err and ! empty($contents)) {
                    if (@file_put_contents($file, $contents, LOCK_EX)) @chmod($file, 0777);
                    else @unlink($file);
                } else return null;
            }
            $path = realpath($file);
        }
        return $path;
    }

    /**
     * Prevents a bug in Curl that prevents some properties from being written using curl_setopt_array
     * @param resource $ch
     * @param array $options
     */
    private function curl_setopt_array($ch, array $options) {
        foreach ($options as $k => $v) {
            curl_setopt($ch, $k, $v);
        }
    }

    /**
     * Checks if URL is valid
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool {
        return preg_match(CurlHelper::VALID_URL_REGEX, $url) > 0;
    }

    /**
     * Encode key value pairs to a valid curl input
     * @return array
     */
    private function makeHeaders(): array {
        $lines = [];
        foreach ($this->headers as $k => $v) {
            $lines[] = sprintf('%s: %s', $k, $v);
        }
        return $lines;
    }

    /** @return static */
    private function getClone(): self {
        return clone $this;
    }

    /**
     * @param int $curlopt
     * @param mixed $value
     * @return static
     */
    private function setOpt(int $curlopt, $value) {
        $this->opts[$curlopt] = $value;
        return $this;
    }

    /**
     * Adds an header
     * @param string $key
     * @param string $value
     * @return static
     */
    private function setHeader(string $key, string $value): self {
        $this->headers[$key] = $value;
        return $this;
    }

    ////////////////////////////   BASIC BUILDER   ////////////////////////////

    /**
     * Add an Option to curl
     * @param int $curlopt
     * @param mixed $value
     * @return static
     */
    public function withOpt(int $curlopt, $value): self {
        return $this->getClone()->setOpt($curlopt, $value);
    }

    /**
     * Add the given options to curl
     * @param array<int,mixed> $opts
     * @return static
     */
    public function withOpts(array $opts): self {
        if (empty($opts)) return $this;
        $clone = $this->getClone();
        foreach ($opts as $opt => $val) {
            $clone->setOpt($opt, $val);
        }
        return $clone;
    }

    /**
     * Set multiple headers (overwrites the headers)
     * @param array<string,string> $headers
     * @return static
     */
    public function withHeaders(array $headers): self {
        $clone = $this->getClone();
        $clone->headers = [];
        // Type Check
        foreach ($headers as $k => $v) {
            $clone->setHeader($k, $v);
        }
        return $clone;
    }

    /**
     * Adds multiple headers to the stack
     * @param array<string,string> $headers
     * @return static
     */
    public function withAddedHeaders(array $headers): self {
        $clone = $this->getClone();
        foreach ($headers as $k => $v) {
            $clone->setHeader($k, $v);
        }
        return $clone;
    }

    /**
     * Add a single header to the stack
     * @param string $name
     * @param string $value
     * @return static
     */
    public function withHeader(string $name, string $value): self {
        return $this->getClone()->setHeader($name, $value);
    }

    ////////////////////////////   ADVANCED BUILDER   ////////////////////////////

    /**
     * Add Basic authorization to request
     * @param string $user
     * @param string $password
     * @return static
     */
    public function withAuth(string $user, string $password) {
        return $this->withHeader("Authorization", sprintf("Basic %s", base64_encode("$user:$password")));
    }

    /**
     * Set the Referer
     * @param string $referer
     * @return static
     */
    public function withReferer(string $referer) {
        return $this->withOpt(CURLOPT_REFERER, $referer);
    }

    /**
     * Add AJAX Header
     * @return static
     */
    public function withAJAX() {
        return $this->withHeader("X-Requested-With", "XMLHttpRequest");
    }

    /**
     * Set the proxy for the request
     * @param string $protocol  http|https|socks4|socks5
     * @param string $host
     * @param int $port
     * @return static
     * @throws InvalidArgumentException
     */
    public function withProxy(string $protocol, string $host, int $port) {
        if (!in_array($protocol, ['http', 'https', 'socks4', 'socks5'])) {
            throw new InvalidArgumentException("Invalid protocol $protocol for proxy");
        }
        return $this->withOpts([
                    CURLOPT_HTTPPROXYTUNNEL => 0,
                    CURLOPT_PROXY => sprintf("%s://%s:%d", $protocol, $host, $port)
        ]);
    }

    /**
     * POST JSON Data
     * @link https://lornajane.net/posts/2011/posting-json-data-with-php-curl
     * @param string $json
     * @return static
     */
    public function postJson(string $json) {
        return $this->withHeaders([
                    "Content-Type" => "application/json",
                    "Content-Length" => (string) strlen($json)
                ])->withOpts([
                    CURLOPT_CUSTOMREQUEST => "POST",
                    CURLOPT_POSTFIELDS => $json
        ]);
    }

    /**
     * POST data as key value pairs
     * @param array<string,mixed> $data
     * @return static
     */
    public function postData(array $data) {
        return $this->withOpts([
                    CURLOPT_POSTREDIR => CURL_REDIR_POST_ALL,
                    CURLOPT_POSTFIELDS => http_build_query($data)
        ]);
    }

    /**
     * Set a cookie location for that request
     * @param string $cookieFile File where will be stored the cookies
     * @return static
     * @throws InvalidArgumentException
     */
    public function withCookieFile(string $cookieFile) {
        $dirname = dirname($cookieFile);
        if (!is_dir($dirname) or ! is_writable($dirname)) {
            throw new InvalidArgumentException("$dirname for cookie file does not exists or is not writable.");
        }
        $clone = $this->getClone();
        $clone->cookie = $cookieFile;
        return $clone;
    }

    /**
     * Set User Agent for the Request
     * @param string $userAgent
     * @return static
     */
    public function withUserAgent(string $userAgent) {
        return $this->withOpt(CURLOPT_USERAGENT, $userAgent);
    }

    /**
     * Set number of retry (on timeout)
     * @param int $retry
     * @return static
     */
    public function withRetry(int $retry) {
        $clone = $this->getClone();
        $clone->retry = $retry;
        return $clone;
    }

    /**
     * Set Request and Connection timeout for the request
     * @param int $timeout
     * @return static
     */
    public function withTimeout(int $timeout) {
        return $this->withOpts([
                    CURLOPT_CONNECTTIMEOUT => $timeout,
                    CURLOPT_TIMEOUT => $timeout
        ]);
    }

    ////////////////////////////   FETCH   ////////////////////////////

    public function fetch(string $url): CurlResponse {

    }

}
