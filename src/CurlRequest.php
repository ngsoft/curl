<?php

declare(strict_types=1);

namespace NGSOFT\Curl;

use InvalidArgumentException,
    NGSOFT\Curl\Interfaces\Curl,
    RuntimeException;

class CurlRequest {

    /**
     * Current Version
     */
    const VERSION = "1.2.1";

    /**
     * Certificats to Enable Secure HTTPS
     */
    const CACERT_SRC = 'https://curl.haxx.se/ca/cacert.pem';

    /** Mozilla Firefox ESR */
    const USER_AGENT = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:68.0) Gecko/20100101 Firefox/68.0";

    /**
     * Curl Defaults to be extended By User params
     */
    const CURL_DEFAULTS = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "gzip,deflate", //some sites encoding gives an encoded body in the response
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_AUTOREFERER => true,
        /** @link https://curl.haxx.se/libcurl/c/CURLOPT_COOKIEFILE.html Enables cookie engine without using a file */
        CURLOPT_COOKIEFILE => "",
    ];

    /** @var string|null */
    private static $cacertLocation;

    /** @var array<string,string[]> */
    private $headers = [];

    /** @var int */
    private $retry = 0;

    /** @var array<int,mixed> */
    private $opts = [];

    /** @var string|null */
    private $cookie;

    ////////////////////////////   UTILS   ////////////////////////////

    /**
     * Create a new instance
     * @return static
     */
    public static function create(): self {
        return new static();
    }

    /**
     * Set the Certifications download folder
     * @param string $certlocation
     * @throws InvalidArgumentException
     */
    public static function setCertlocation(string $certlocation) {
        file_exists($certlocation) || @mkdir($certlocation, 0777, true);
        if (!is_dir($certlocation) or!is_writable($certlocation)) {
            throw new RuntimeException("$certlocation is not an existing directory or is not writable.");
        }
        self::$cacertLocation = $certlocation . DIRECTORY_SEPARATOR . basename(self::CACERT_SRC);
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
                if (!$err and!empty($contents)) {
                    if (@file_put_contents($file, $contents, LOCK_EX)) @chmod($file, 0777);
                    else @unlink($file);
                } else return null;
            }
            $path = realpath($file) ?: null;
        }
        return $path;
    }

    /**
     * Proxy out curl_setopt
     * @param resource $ch
     * @param int $opt
     * @param mixed $value
     * @return void
     * @throws InvalidArgumentException
     */
    private function curl_setopt($ch, int $opt, $value): void {
        if (curl_setopt($ch, $opt, $value) === false) {
            throw new InvalidArgumentException(
                    "Invalid CURLOPT $opt"
            );
        }
    }

    /**
     * Prevents a bug in Curl that prevents some properties from being written using curl_setopt_array
     * @param resource $ch
     * @param array $options
     */
    private function curl_setopt_array($ch, array $options) {

        foreach ($options as $k => $v) {
            $this->curl_setopt($ch, $k, $v);
        }
    }

    /**
     * Checks if URL is valid
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool {
        return preg_match(Curl::VALID_URL_REGEX, $url) > 0;
    }

    /**
     * Encode key value pairs to a valid curl input
     * @return array<string>
     */
    private function makeHeaders(): array {
        $lines = [];
        foreach ($this->headers as $name => $v) {
            foreach ($v as $val) {
                $lines[] = sprintf('%s: %s', $name, $val);
            }
        }
        return $lines;
    }

    /**
     * Convert text header to valid array header
     * @param string $header
     * @return array<string,string[]>
     */
    private function parseHeaderText(string $header): array {
        $lines = explode("\n", $header);
        $headers = [];
        $matches = [];
        foreach ($lines as $line) {
            if (!empty($line) and preg_match('/(?:(\S+):\s(.*))/', $line, $matches) > 0) {
                list(, $name, $value) = $matches;
                $headers[$name][] = trim($value);
            }
        }
        return $headers;
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
        $this->headers[$key][] = $value;
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
     * @param array<string,string|string[]> $headers
     * @return static
     */
    public function withHeaders(array $headers): self {
        $clone = $this->getClone();
        $clone->headers = [];
        // Type Check
        foreach ($headers as $k => $v) {
            if (!is_array($v)) $v = [$v];
            foreach ($v as $val) {
                $clone->setHeader($k, $val);
            }
        }
        return $clone;
    }

    /**
     * Adds multiple headers to the stack
     * @param array<string,string|string[]> $headers
     * @return static
     */
    public function withAddedHeaders(array $headers): self {
        $clone = $this->getClone();
        foreach ($headers as $k => $v) {
            if (!is_array($v)) $v = [$v];
            foreach ($v as $val) {
                $clone->setHeader($k, $val);
            }
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

    /**
     * Adds multiple headers to the stack
     * @param string $header
     * @return static
     */
    public function withAddedHeaderText(string $header): self {
        return $this->withAddedHeaders($this->parseHeaderText($header));
    }

    /**
     * Set multiple headers (overwrites the headers)
     * @param string $header
     * @return static
     */
    public function withHeaderText(string $header): self {
        return $this->withHeaders($this->parseHeaderText($header));
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
     * @throws RuntimeException
     */
    public function withCookieFile(string $cookieFile) {
        $dirname = dirname($cookieFile);
        file_exists($dirname)or @ mkdir($dirname, 0777, true);
        if (!is_dir($dirname) or!is_writable($dirname)) {
            throw new RuntimeException("$dirname for cookie file does not exists or is not writable.");
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
    public function withUserAgent(string $userAgent = self::USER_AGENT) {
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

    /**
     * Tells CURL if it should follow redirection (301,302)
     * @param bool $redirect
     * @return static
     */
    public function withAutoRedirect(bool $redirect = true) {
        return $this->withOpt(CURLOPT_FOLLOWLOCATION, $redirect);
    }

    /**
     * Set the request method
     * @param string $method
     * @return static
     * @throws InvalidArgumentException
     */
    public function withMethod(string $method) {
        $method = strtoupper($method);
        if (!in_array($method, Curl::VALID_METHODS)) throw new InvalidArgumentException("Invalid method $method");
        return $this->withOpt(CURLOPT_CUSTOMREQUEST, $method);
    }

    /**
     * Set the URL
     * @param string $url
     * @return static
     * @throws InvalidArgumentException
     * @return static
     */
    public function withUrl(string $url) {
        if (!$this->isValidUrl($url)) throw new InvalidArgumentException("Invalid URL $url");
        return $this->withOpt(CURLOPT_URL, $url);
    }

    /**
     * Add Data to post
     * @param null|string|array<string,mixed> $data
     * @throws InvalidArgumentException
     * @return static
     */
    public function withData($data) {
        if (is_array($data)) $data = http_build_query($data);
        if (!is_string($data)) {
            throw new InvalidArgumentException(
                    "Invalid data supplied, string|array requested but "
                    . gettype($data) . " given."
            );
        }
        return $this->withOpt(CURLOPT_POSTFIELDS, $data);
    }

    ////////////////////////////   FETCH   ////////////////////////////

    /**
     * Execute the CURL Request
     * @param string|null $url
     * @param string|null $method GET|HEAD|POST|PUT|DELETE|CONNECT|OPTIONS|TRACE|PATCH
     * @param null|string|array<string,mixed> $data
     * @throws InvalidArgumentException
     * @return CurlResponse
     */
    public function fetch(string $url = null, string $method = null, $data = null): CurlResponse {
        /**
         * Assertions
         */
        if ($url === null) {
            if (!isset($this->opts[CURLOPT_URL])) throw new InvalidArgumentException("No Url Defined.");
            $url = $this->opts[CURLOPT_URL];
        }
        if (!$this->isValidUrl($url)) throw new InvalidArgumentException("Invalid URL $url");
        if ($method !== null and ( $parsedmethod = strtoupper($method)) and!in_array($parsedmethod, Curl::VALID_METHODS)) {
            throw new InvalidArgumentException("Invalid Method $method");
        }

        if (is_array($data)) $data = http_build_query($data);
        if (isset($data) and!is_string($data)) {
            throw new InvalidArgumentException(
                    "Invalid data supplied, string|array|NULL requested but "
                    . gettype($data) . " given."
            );
        }
        /**
         * Build the request
         */
        $ch = $this->initCurl();
        $this->curl_setopt($ch, CURLOPT_URL, $url);
        if (isset($parsedmethod)) $this->curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $parsedmethod);
        if (isset($data)) $this->curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        return CurlResponse::from($this->execCurl($ch));
    }

    /**
     * Initialize CURL resource
     * @return resource
     */
    private function initCurl() {
        $ch = curl_init();
        $headers = [CURLOPT_HTTPHEADER => $this->makeHeaders()];
        $cookies = [];
        if (!empty($this->cookie)) {
            $cookies = [
                CURLOPT_COOKIEFILE => $this->cookie,
                CURLOPT_COOKIEJAR => $this->cookie
            ];
        }
        $ca = [CURLOPT_SSL_VERIFYPEER => false];
        if ($cert = $this->getCACert()) {
            $ca = [
                CURLOPT_CAINFO => $cert,
                CURLOPT_SSL_VERIFYPEER => true
            ];
        }
        $this->curl_setopt_array($ch, $headers);
        $this->curl_setopt_array($ch, array_replace(self::CURL_DEFAULTS, $cookies, $ca, $this->opts));
        return $ch;
    }

    /**
     * Execute CURL Request
     * @param resource $ch
     * @return array<string,mixed>
     */
    private function execCurl($ch): array {
        assert(is_resource($ch));

        $this->curl_setopt($ch, CURLINFO_HEADER_OUT, true); // to parse request headers

        $filehandle = $this->opts[CURLOPT_FILE] ?? fopen("php://temp", "r+");
        if (!isset($this->opts[CURLOPT_FILE])) $this->curl_setopt($ch, CURLOPT_FILE, $filehandle);

        $fullheader = "";
        $headers = [];
        $status = 200;
        $version = "1.1";
        $this->curl_setopt($ch, CURLOPT_HEADERFUNCTION, function () use (&$fullheader, &$headers, &$status, &$version) {
            list(, $header) = func_get_args();
            $fullheader .= $header;
            $len = strlen($header);
            $line = trim($header);
            $matches = [];
            if (!empty($line) and preg_match('/(?:(\S+):\s(.*))/', $line, $matches) > 0) {
                list(, $name, $value) = $matches;
                $headers[$name][] = trim($value);
            } elseif (!empty($line) and preg_match('/[A-Z]+\/([0-9](?:\.[0-9])?)\h+([0-9]{3})/i', $line, $matches)) {
                list(, $version, $status) = $matches;
                $status = intval($status);
                $version = strlen($version) > 1 ? $version : "$version.0";
            }
            return $len;
        });

        // Retry on timeout
        $try = $this->retry + 1;
        do {
            $success = curl_exec($ch);
            $err = curl_error($ch);
            $errno = curl_errno($ch);
            if ($errno !== CURLE_OPERATION_TIMEOUTED) break;
            --$try;
        } while ($try > 0);

        // Parse Request Headers
        $rheaders = [];
        if (($hout = curl_getinfo($ch, CURLINFO_HEADER_OUT))) {
            $matches = [];
            foreach (explode("\n", $hout) as $line) {
                $line = trim($line);
                if (!empty($line) and preg_match('/(?:(\S+):\s(.*))/', $line, $matches) > 0) {
                    list(, $name, $value) = $matches;
                    $rheaders[$name][] = trim($value);
                }
            }
        }


        $result = [
            "curl_info" => null,
            "curl_exec" => $success,
            "curl_error" => $err,
            "curl_errno" => $errno,
            "curl_resource" => $ch,
            "url" => curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
            "status" => curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            "statustext" => Curl::REASON_PHRASES[$status] ?? Curl::UNASSIGNED_REASON_PHRASE,
            "version" => $version,
            "content_type" => curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: "",
            "redirect_count" => curl_getinfo($ch, CURLINFO_REDIRECT_COUNT),
            "redirect_url" => curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: "",
            "headers" => $headers,
            "header_size" => curl_getinfo($ch, CURLINFO_HEADER_SIZE),
            "request_headers" => $rheaders,
            "body" => $filehandle,
            "request" => $this,
        ];

        return $result;
    }

}
