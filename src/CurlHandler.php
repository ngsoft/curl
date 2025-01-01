<?php


class CurlHandler
{

    /**
     * Experimental technology to fetch long list of urls faster
     * Only supports GET method with no header parsing
     *
     * @param string[]|Stringable[] $urls
     * @return HttpClient\CurlResponse[] returns responses in order of urls
     */
    public static function makeMultiGetRequests(array $urls)
    {
        $multi = new HttpClient\CurlMultiRequest();
        $cookies = tempnam(sys_get_temp_dir(), 'curl_multi');
        foreach ($urls as $url) {

            $req = (new HttpClient\CurlRequest());
            $multi->add(
                $req
                    // prevent multi handler to follow using synchronous request
                    ->setOpt(CURLOPT_FOLLOWLOCATION, true)
                    // as headers cannot be defined in that function
                    // make believe we are in the last firefox version
                    ->setUserAgent(self::generateUserAgent())
                    // cookie support if needed
                    ->setCookieFile($cookies)
                    ->prepare(self::METHOD_GET, $url)
            );
        }

        // make request
        return $multi->execute()->getResults();
    }


    /**
     * @param string|Stringable $url
     * @param null|string|array<string, string> $params
     * @param string|Stringable $method
     * @param ?array<string, string|string[]> $headers
     * @param int $timeout
     *
     * @return HttpClient\CurlResponse
     */
    public static function makeHttpRequest($url, $params = null, $method = 'GET', $headers = null, $timeout = 0)
    {


        $req = new HttpClient\CurlRequest();
        $req->enableHeaderParsing();

        if (is_int($headers)) {
            $timeout = $headers;
            $headers = null;
        }

        if (is_array($method)) {
            $headers = $method;
            $method = "GET";
        }

        if (is_array($headers)) {

            $usable = [];

            foreach ($headers as $name => $val) {
                if (strtolower($name) === "cookie-file") {
                    $req->setCookieFile($val);
                    continue;
                }
                $usable[$name] = $val;
            }
            $req->setHeaders($usable);
        }


        if ($timeout > 0) {
            $req->setTimeout($timeout);
        }

        try {
            return $req->fetch($method, $url, $params);
        } finally {
            $req->closeHandle();
        }


    }

    /**
     * @param string $method
     * @param bool $normalize
     * @return bool
     */
    public static function isValidMethod($method, $normalize = true)
    {
        if ($normalize) {
            $method = strtoupper($method);
        }
        return in_array($method, self::$VALID_METHODS);
    }

    /**
     * @param string|int|null|bool $version true => random, null|false => latest, int => "$version.0"
     * @return string
     */
    public static function generateUserAgent($version = null)
    {

        /**
         * @link https://wiki.mozilla.org/Release_Management/Product_details
         */
        static $ffListApi = "https://product-details.mozilla.org/1.0/firefox_history_major_releases.json",
        $ffLastApi = "https://product-details.mozilla.org/1.0/firefox_versions.json",
        $template = "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:{version}) Gecko/20100101 Firefox/{version}";


        if (!isset(self::$firefoxVersions)) {

            $cachedFile = sys_get_temp_dir() . "/curl_firefox_versions.json";
            $cachedData = false;
            @mkdir(dirname($cachedFile), 0777, true);

            if (@filemtime($cachedFile) > time() - 3600) {

                $cachedData = @file_get_contents($cachedFile);

                if (is_string($cachedData)) {
                    $cachedData = json_decode($cachedData, true);
                }
            }

            if (!$cachedData) {
                $versions = [];

                if ($list = self::makeSimpleGetHttpRequest($ffListApi)) {
                    foreach (array_reverse($list) as $ver => $date) {

                        if (strtotime($date) < strtotime("-3 years")) {
                            continue;
                        }
                        if (!preg_match("#^\d+\.\d+$#", $ver)) {
                            continue;
                        }
                        if (strtotime($date) < time()) {
                            $versions[] = $ver;
                        }
                    }
                }

                $latest = self::$latestFirefoxVersion;

                if (!empty($versions)) {
                    $latest = $versions[0];
                }

                $data = self::makeSimpleGetHttpRequest($ffLastApi);
                if ($data) {
                    $latest = $data['LATEST_FIREFOX_VERSION'];
                }

                if (!empty($versions)) {
                    $cachedData = [$versions, $latest];
                    @file_put_contents($cachedFile, json_encode($cachedData));

                }
            }


            if ($cachedData) {
                list(self::$firefoxVersions, self::$latestFirefoxVersion) = $cachedData;
            }
        }


        if (!empty($version)) {

            if (is_int($version)) {
                $version = "$version.0";
            } elseif (true === $version) {
                $version = self::$firefoxVersions[array_rand(self::$firefoxVersions)];
            }


        } else {
            $version = self::$latestFirefoxVersion;
        }

        $version = preg_replace('#^(\d+\.\d+)\D*.*$#', '$1', $version);

        return str_replace("{version}", $version, $template);


    }

    public static function makeSimpleGetHttpRequest($url)
    {
        $json = @file_get_contents(
            $url,
            false,
            stream_context_create([
                'http' => ['method' => 'GET'],
                'ssl' => [
                    "verify_peer" => false,
                    "verify_peer_name" => false
                ]
            ])
        ) ?: "";
        if (null === $decoded = @json_decode($json, true)) {
            return $json;
        }
        return $decoded;
    }

    public static function getReasonPhrase($statusCode)
    {
        return isset(self::$REASON_PHRASES[$statusCode]) ? self::$REASON_PHRASES[$statusCode] : self::$REASON_PHRASES[0];
    }

    protected static $firefoxVersions = null;
    protected static $latestFirefoxVersion = "132.0";
    /**
     * @link https://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
     */
    protected static $REASON_PHRASES = [
        "Unassigned",
        100 => "Continue",
        101 => "Switching Protocols",
        102 => "Processing",
        103 => "Early Hints",
        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",
        207 => "Multi-Status",
        208 => "Already Reported",
        226 => "IM Used",
        300 => "Multiple Choices",
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        304 => "Not Modified",
        305 => "Use Proxy",
        307 => "Temporary Redirect",
        308 => "Permanent Redirect",
        400 => "Bad Request",
        401 => "Unauthorized",
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",
        408 => "Request Timeout",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",
        413 => "Payload Too Large",
        414 => "URI Too Long",
        415 => "Unsupported Media Type",
        416 => "Range Not Satisfiable",
        417 => "Expectation Failed",
        421 => "Misdirected Request",
        422 => "Unprocessable Entity",
        423 => "Locked",
        424 => "Failed Dependency",
        425 => "Too Early",
        426 => "Upgrade Required",
        428 => "Precondition Required",
        429 => "Too Many Requests",
        431 => "Request Header Fields Too Large",
        451 => "Unavailable For Legal Reasons",
        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
        505 => "HTTP Version Not Supported",
        506 => "Variant Also Negotiates",
        507 => "Insufficient Storage",
        508 => "Loop Detected",
        510 => "Not Extended",
        511 => "Network Authentication Required",
    ];


    const METHOD_GET = "GET";
    const METHOD_HEAD = "HEAD";
    const METHOD_POST = "POST";
    const METHOD_PUT = "PUT";
    const METHOD_DELETE = "DELETE";
    const METHOD_CONNECT = "CONNECT";
    const METHOD_OPTIONS = "OPTIONS";
    const METHOD_TRACE = "TRACE";
    const METHOD_PATCH = "PATCH";


    /**
     * Valid Methods
     */
    protected static $VALID_METHODS = [
        self::METHOD_GET,
        self::METHOD_HEAD,
        self::METHOD_POST,
        self::METHOD_PUT,
        self::METHOD_DELETE,
        self::METHOD_CONNECT,
        self::METHOD_OPTIONS,
        self::METHOD_TRACE,
        self::METHOD_PATCH,
    ];

}