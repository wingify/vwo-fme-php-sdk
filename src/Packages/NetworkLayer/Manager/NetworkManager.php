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

namespace vwo\Packages\NetworkLayer\Manager;

use vwo\Packages\NetworkLayer\Client\NetworkClient;
use vwo\Packages\NetworkLayer\Models\GlobalRequestModel;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Packages\NetworkLayer\Handlers\RequestHandler;
use Exception;

class NetworkManager {
    private $config;
    private $client;
    private static $instance;

    public function attachClient($client = null): void {
        $this->client = $client ?: new NetworkClient();
        $this->config = new GlobalRequestModel(null, null, null, null);
    }

    public static function instance(): NetworkManager {
        self::$instance = self::$instance ?: new NetworkManager();
        return self::$instance;
    }

    public function setConfig($config): void {
        $this->config = $config;
    }

    public function getConfig(): GlobalRequestModel {
        return $this->config;
    }

    public function createRequest($request): RequestModel {
        $options = (new RequestHandler())->createRequest($request, $this->config);
        return $options;
    }

    public function get($request) {
        $networkOptions = $this->createRequest($request);

        if ($networkOptions === null) {
            throw new \Exception('no url found');
        }

        try {
            $response = $this->client->GET($networkOptions);
            return $response;
        } catch (Exception $e) {
            throw new \Exception('Failed to make GET request: ' . $e->getMessage());
        }
    }

    public function post($request) {
        $networkOptions = $this->createRequest($request);

        if ($networkOptions === null) {
            throw new \Exception('no url found');
        }

        try {
            $response = $this->client->POST($networkOptions);
            return $response;
        } catch (Exception $e) {
            throw new \Exception('Failed to make POST request: ' . $e->getMessage());
        }
    }
}

?>
