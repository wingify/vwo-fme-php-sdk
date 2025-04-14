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

class GlobalRequestModel {
    private $url;
    private $timeout = 3000;
    private $query;
    private $body;
    private $headers;
    private $isDevelopmentMode;

    public function __construct($url, $query, $body, $headers) {
        $this->url = $url;
        $this->query = $query;
        $this->body = $body;
        $this->headers = $headers;
    }

    public function setQuery($query) {
        $this->query = $query;
    }

    public function getQuery() {
        return $this->query;
    }

    public function setBody($body) {
        $this->body = $body;
    }

    public function getBody() {
        return $this->body;
    }

    public function setBaseUrl($url) {
        $this->url = $url;
    }

    public function getBaseUrl() {
        return $this->url;
    }

    public function setTimeout($timeout) {
        $this->timeout = $timeout;
    }

    public function getTimeout() {
        return $this->timeout;
    }

    public function setHeaders($headers) {
        $this->headers = $headers;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function setDevelopmentMode($isDevelopmentMode) {
        $this->isDevelopmentMode = $isDevelopmentMode;
    }

    public function getDevelopmentMode() {
        return $this->isDevelopmentMode;
    }
}

?>
