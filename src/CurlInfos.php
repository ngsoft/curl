<?php

namespace NGSOFT\Curl;

use ArrayAccess,
    Countable,
    IteratorAggregate,
    NGSOFT\Curl\Exceptions\CurlException,
    RuntimeException;

/*
 * Properties that exists but not needed
 * @property double $total_time
 * @property double $namelookup_time
 * @property double $connect_time
 * @property double $pretransfer_time
 * @property int $size_upload
 * @property int $size_download
 * @property int $speed_download
 * @property double $starttransfer_time
 * @property string $primary_ip
 * @property int $primary_port
 * @property string $local_ip
 * @property int $local_port
 * @property int $appconnect_time_us
 * @property int $connect_time_us
 * @property int $namelookup_time_us
 * @property int $pretransfer_time_us
 * @property int $starttransfer_time_us
 * @property int $total_time_us
 */

/**
 * @property string $url Last effective URL
 * @property int $status HTTP Status Code
 * @property string $statustext HTTP Status Text
 * @property string $version HTTP Protocol Version
 * @property string $content_type Content-Type
 * @property int $redirect_count Number of redirects
 * @property string $redirect_url Next url to redirect to
 * @property array<string,string[]> $headers Parsed Headers
 * @property string $header_string Non parsed headers
 * @property int $header_size Header Size
 * @property array<string,string[]> $request_headers Parsed Request headers
 * @property bool $curl_exec curl_exec() return value
 * @property string $curl_error curl_error() return value
 * @property int $curl_errno curl_errno() return value
 * @property stdClass $curl_info curl_info() return value converted to object
 * @property resource $body File handle containing the contents
 * @property string $contents Getter to retrieve the contents
 */
class CurlInfos implements ArrayAccess, Countable {

    /** @var array<string,mixed> */
    private $storage = [];

    /**
     * Creates a new CurlResponse using the metadatas provided
     * @param array $metadatas
     * @return static
     */
    public static function create(array $metadatas): self {
        self::assertValidMetadatas($metadatas);
        $response = new static();
        $response->storage = $metadatas;
        return $response;
    }

    private static function assertValidMetadatas(array $metadatas) {
        foreach ([
    "url",
    "status",
    "statustext",
    "version",
    "content_type",
    "redirect_count",
    "redirect_url",
    "headers",
    "header_string",
    "header_size",
    "request_headers",
    "curl_exec",
    "curl_error",
    "curl_errno",
    "curl_info",
    "body",
        ] as $key) {
            if (!array_key_exists($key, $metadatas)) {
                throw new RuntimeException("Invalid Metadata Provided.");
            }
        }
    }

    /**
     * Get Contents
     * @return string
     * @throws CurlException
     */
    private function getContents(): string {
        if (-1 === fseek($this->body, 0)) {
            throw new CurlException("Cannot seek stream.", CurlException::CODE_INVALIDSTREAM);
        }
        return stream_get_contents($this->body);
    }

    /**
     * Does nothing
     */
    private function noop() {

    }

    ////////////////////////////   Magic Methods   ////////////////////////////

    /** {@inheritdoc} */
    public function __get($name) {
        if (isset($this->{$name})) return $this->storage[$name];
        elseif ($name === "contents") return $this->getContents();
        elseif ($name = "redirect_url") return "";
        throw new RuntimeException("Invalid property $name");
    }

    /** {@inheritdoc} */
    public function __isset($name) {
        return array_key_exists($name, $this->storage);
    }

    /** {@inheritdoc} */
    public function __set($name, $value) {
        $this->noop($name, $value);
    }

    /** {@inheritdoc} */
    public function __unset($name) {
        $this->noop($name);
    }

    /**
     * Returns contents from the stream
     * @return string
     */
    public function __toString() {
        try {
            return $this->getContents();
        } catch (CurlException $exc) {
            $exc->getCode();
            return "";
        }
    }

    ////////////////////////////   ArrayAccess...   ////////////////////////////

    /** {@inheritdoc} */
    public function offsetExists($offset) {
        return $this->__isset($offset);
    }

    /** {@inheritdoc} */
    public function offsetGet($offset) {
        return $this->__get($offset);
    }

    /** {@inheritdoc} */
    public function offsetSet($offset, $value) {
        $this->noop($offset, $value);
    }

    /** {@inheritdoc} */
    public function offsetUnset($offset) {
        $this->noop($offset);
    }

    /** {@inheritdoc} */
    public function count() {
        $count = count($this->storage);
        return $count ? $count + 1 : 0;
    }

}
