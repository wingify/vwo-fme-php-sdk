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

namespace vwo\Packages\NetworkLayer\Manager;

use vwo\Packages\NetworkLayer\Client\NetworkClient;
use vwo\Packages\NetworkLayer\Models\GlobalRequestModel;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Packages\NetworkLayer\Handlers\RequestHandler;
use vwo\Constants\Constants;
use vwo\Services\LoggerService;
use Exception;

class NetworkManager {
    private $config;
    private $client;
    private static $instance;
    private $isGatewayUrlNotSecure = false; // Store the flag here
    private $shouldWaitForTrackingCalls = false;
    private $retryConfig = Constants::DEFAULT_RETRY_CONFIG;
    private $logManager;

    // public function __construct() {
    //     $this->config = new GlobalRequestModel(null, null, null, null);
    // }

    public function attachClient($client = null, $options = []) {
        $this->config = new GlobalRequestModel(null, null, null, null);
        if(isset($options['isGatewayUrlNotSecure'])) {
            $this->isGatewayUrlNotSecure = $options['isGatewayUrlNotSecure'];
        }
        if(isset($options['shouldWaitForTrackingCalls'])) {
            $this->shouldWaitForTrackingCalls = $options['shouldWaitForTrackingCalls'];
        }
        if(isset($options['retryConfig'])) {
            $this->retryConfig = $options['retryConfig'];
        }
        if(isset($options['logManager'])) {
            $this->logManager = $options['logManager'];
        }
        // Normalize retry config from options (if any)
        $providedRetry = isset($options['retryConfig']) && is_array($options['retryConfig']) ? $options['retryConfig'] : [];
        $this->retryConfig = $this->validateRetryConfig($providedRetry);

        // Initialize client without retryConfig; manager owns it
        $clientOptions = [
            'isGatewayUrlNotSecure' => $this->isGatewayUrlNotSecure,
            'shouldWaitForTrackingCalls' => $this->shouldWaitForTrackingCalls,
            'retryConfig' => $this->retryConfig,
            'logManager' => $this->logManager,
        ];
        $this->client = $client ?: new NetworkClient($clientOptions);
    }

     // Singleton pattern
     public static function instance($options = null): NetworkManager {
        if (!self::$instance) {
            self::$instance = new NetworkManager();
        }

        // Set options only once when the instance is first created
        if ($options && !self::$instance->isInitialized()) {
            self::$instance->attachClient(new NetworkClient(), $options); // Pass options during first initialization
        }
        
        return self::$instance;
    }

    // Check if the instance has been initialized
    private function isInitialized() {
        return isset($this->isGatewayUrlNotSecure) && isset($this->shouldWaitForTrackingCalls);
    }

    public function setConfig($config) {
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
        if ($this->client === null) {
            throw new \Exception('NetworkManager client is not initialized. Please call attachClient() first.');
        }

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
        if ($this->client === null) {
            throw new \Exception('NetworkManager client is not initialized. Please call attachClient() first.');
        }

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

    public function getRetryConfig() {
        return $this->retryConfig;
    }

    private function validateRetryConfig($retryConfig) {
        $validated = Constants::DEFAULT_RETRY_CONFIG;
        $invalid = false;

        if (isset($retryConfig[Constants::RETRY_SHOULD_RETRY])) {
            if (is_bool($retryConfig[Constants::RETRY_SHOULD_RETRY])) {
                $validated[Constants::RETRY_SHOULD_RETRY] = $retryConfig[Constants::RETRY_SHOULD_RETRY];
            } else { 
                $invalid = true; 
            }
        }
        if (isset($retryConfig[Constants::RETRY_MAX_RETRIES])) {
            if (is_int($retryConfig[Constants::RETRY_MAX_RETRIES]) && $retryConfig[Constants::RETRY_MAX_RETRIES] >= 1) {
                $validated[Constants::RETRY_MAX_RETRIES] = $retryConfig[Constants::RETRY_MAX_RETRIES];
            } 
            else { 
                $invalid = true;
            }
        }
        if (isset($retryConfig[Constants::RETRY_INITIAL_DELAY])) {
            if (is_int($retryConfig[Constants::RETRY_INITIAL_DELAY]) && $retryConfig[Constants::RETRY_INITIAL_DELAY] >= 1) {
                $validated[Constants::RETRY_INITIAL_DELAY] = $retryConfig[Constants::RETRY_INITIAL_DELAY];
            } else { 
                $invalid = true; 
            }
        }
        if (isset($retryConfig[Constants::RETRY_BACKOFF_MULTIPLIER])) {
            if (is_int($retryConfig[Constants::RETRY_BACKOFF_MULTIPLIER]) && $retryConfig[Constants::RETRY_BACKOFF_MULTIPLIER] >= 2) {
                $validated[Constants::RETRY_BACKOFF_MULTIPLIER] = $retryConfig[Constants::RETRY_BACKOFF_MULTIPLIER];
            } else { 
                $invalid = true; 
            }
        }

        if ($invalid) {
            LoggerService::error('INVALID_RETRY_CONFIG', [ 'retryConfig' => json_encode($retryConfig) ]);
        }

        return $validated;
    }
}

?>
