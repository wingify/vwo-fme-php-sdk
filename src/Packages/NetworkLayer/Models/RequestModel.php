<?php

/**
 * Copyright 2024-2025 Wingify Software Pvt. Ltd.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace vwo\Packages\NetworkLayer\Models;

class RequestModel {
    private $url;
    private $method;
    private $scheme;
    private $port;
    private $path;
    private $query;
    private $timeout;
    private $body;
    private $headers;

    public function __construct(
        $url,
        $method = 'GET',
        $path = '',
        $query = [],
        $body = null,
        $headers = [],
        $scheme = 'http',
        $port = '80'
    ) {
        $this->url = $url;
        $this->path = $path;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
        $this->scheme = $scheme ?? 'http';
        $this->port = $port;
        $this->method = $method;
    }

    public function getMethod() {
        return $this->method;
    }

    public function setMethod($method) {
        $this->method = $method;
    }

    public function getBody() {
        return $this->body;
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function setQuery($query) {
        $this->query = $query;
    }

    public function getQuery() {
        return $this->query;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
        return $this;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function setTimeout($timeout) {
        $this->timeout = $timeout;
        return $this;
    }

    public function getTimeout() {
        return $this->timeout;
    }

    public function getUrl() {
        return $this->url;
    }

    public function setUrl($url) {
        $this->url = $url;
        return $this;
    }

    public function getScheme() {
        return $this->scheme;
    }

    public function setScheme($scheme) {
        $this->scheme = $scheme;
        return $this;
    }

    public function getPort() {
        return $this->port;
    }

    public function setPort($port) {
        $this->port = $port;
        return $this;
    }

    public function getPath() {
        return $this->path;
    }

    public function setPath($path) {
        $this->path = $path;
        return $this;
    }

    public function getOptions() {
        $queryParams = '';
        foreach ($this->query as $key => $value) {
            $queryParams .= "{$key}={$value}&";
        }
        $queryParams = rtrim($queryParams, '&');

        if($this->port)
            $url = "{$this->scheme}://{$this->url}:{$this->port}{$this->path}";
        else {
            $url = "{$this->scheme}://{$this->url}{$this->path}";
        }
        if ($queryParams !== '') {
            $url .= "?{$queryParams}";
        }

        $options = [
            'url' => $url,
            'method' => $this->method,
            'headers' => $this->headers,
        ];

        if ($this->body) {
            $options['body'] = json_encode($this->body);
        }

        if ($this->timeout) {
            $options['timeout'] = $this->timeout;
        }

        return $options;
    }
}

?>
