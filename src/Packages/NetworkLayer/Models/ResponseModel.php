<?php

/**
 * Copyright 2024 Wingify Software Pvt. Ltd.
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

class ResponseModel {
    private $statusCode;
    private $error;
    private $headers;
    private $data;

    public function setStatusCode($statusCode): void {
        $this->statusCode = $statusCode;
    }

    public function setHeaders($headers): void {
        $this->headers = $headers;
    }

    public function setData($data): void {
        $this->data = $data;
    }

    public function setError($error): void {
        $this->error = $error;
    }

    public function getHeaders() {
        return $this->headers;
    }

    public function getData() {
        return $this->data;
    }

    public function getStatusCode() {
        return $this->statusCode;
    }

    public function getError() {
        return $this->error;
    }
}
?>
