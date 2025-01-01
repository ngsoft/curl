<?php

namespace HttpClient;


/**
 * @property-read string $body
 * @property-read int $status
 * @property-read string $statusText
 * @property-read array<int,string> $error
 */
class CurlResponse
{


    /**
     * @var array<string,mixed>
     */
    public $info = null;

    public $success = false;
    protected $contents = null;
    protected $stream = null;
    protected $headers = [];

    public $redirections = 0;

    /**
     * @var array<string,string>
     */
    protected $headerNames = [];


    protected $previous = null;

    /**
     * @return ?static
     */
    public function getPrevious()
    {
        return $this->previous;
    }


    protected function fixHeaders()
    {


        if ($this->stream) {
            $this->contents = null;
        }

        $this->headerNames = [];
        foreach (array_keys($this->headers) as $lowercased) {
            $lowercased = strtolower($lowercased);
            $name = preg_replace_callback("#-\w#", function ($matches) {
                return strtoupper($matches[0]);
            }, ucfirst($lowercased));
            $this->headerNames[$lowercased] = $name;
        }
        $headers = $this->headers;
        $this->headers = [];
        foreach ($this->headerNames as $lower => $name) {
            $this->headers[$name] = $headers[$lower];
        }
    }

    /**
     * @return array
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * @param string $header
     * @return bool
     */
    public function hasHeader($header)
    {
        return isset($this->headerNames[strtolower($header)]);
    }


    /**
     * @param string $header
     * @return array
     */
    public function getHeader($header)
    {
        $header = strtolower($header);

        if (!isset($this->headerNames[$header])) {
            return [];
        }

        $header = $this->headerNames[$header];
        return $this->headers[$header];
    }

    /**
     * @param string $header
     * @return string
     */
    public function getHeaderLine($header)
    {
        return implode(', ', $this->getHeader($header));
    }


    public function __destruct()
    {
        if ($this->stream) {
            @fclose($this->stream);
        }
    }

    /**
     * @param array $data
     * @param ?static $instance
     * @return static
     */
    public static function make(array $data, $instance = null)
    {
        if (!isset($instance)) {
            $instance = new static();
        }
        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }
        $instance->fixHeaders();
        return $instance;
    }

    /**
     * @return ?string
     */
    public function getContents()
    {

        if (!isset($this->contents)) {
            $this->contents = "";
            if ($this->stream) {
                if (-1 !== @fseek($this->stream, 0)) {
                    $this->contents = stream_get_contents($this->stream);
                }
                @fclose($this->stream);
                $this->stream = null;
            }
        }
        return $this->contents;
    }


    /**
     * @return mixed
     */
    public function getDecodedContents()
    {
        $contents = $this->getContents();

        if (null === ($value = @json_decode($contents, true))) {
            $value = $contents;
        }

        return $value;
    }


    public function __get($name)
    {
        if ($name === "body") {
            return $this->getContents();
        }

        if ($this->__isset($name)) {
            return $this->info[$name];
        }

        return null;
    }


    public function __isset($name)
    {
        if ($name === "body") {
            return true;
        }

        return is_array($this->info) && isset($this->info[$name]);
    }


    public function __set($name, $value)
    {
    }

    public function __unset($name)
    {
    }

}