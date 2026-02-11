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

namespace vwo\Services;

use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Utils\NetworkUtil;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Constants\Constants;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use Exception;
use vwo\Models\Schemas\SettingsSchema;
use vwo\Services\LoggerService;

// Defining interface ISettingsService
interface ISettingsService {
    public function getSettings($forceFetch);
    public function fetchSettings();
}

// Defining SettingsService class
class SettingsService implements ISettingsService {
    // Declaring properties
    public $sdkKey;
    public $accountId;
    public $expiry;
    public $networkTimeout;
    public $hostname = Constants::HOST_NAME;
    public $port;
    public $protocol = Constants::HTTPS_PROTOCOL;
    public $isGatewayServiceProvided = false;
    private static $instance;
    public $settingsSchemaValidator;
    public $settingsFetchTime;
    public $isSettingsValidOnInit;
    private $networkManager; // Store instance-based NetworkManager
    private $logManager;
    public $isProxyUrlProvided = false;
    public $proxyUrl = "";
    public static $collectionPrefix;
    // Constructor
    public function __construct($options, $logManager) {
        $this->logManager = $logManager;
        // Assigning values to properties
        $this->sdkKey = $options['sdkKey'];
        $this->accountId = $options['accountId'];
        $this->expiry = isset($options['settingsConfig']['expiry']) ? $options['settingsConfig']['expiry'] : Constants::SETTINGS_EXPIRY;
        $this->networkTimeout = isset($options['settingsConfig']['timeout']) ? $options['settingsConfig']['timeout'] : Constants::SETTINGS_TIMEOUT;

        // check if proxy url is provided and gateway service is also provided
        if (isset($options['proxy']['url']) && !empty($options['proxy']['url']) && isset($options['gatewayService']) && !empty($options['gatewayService'])) {
            $this->logManager->info('PROXY_AND_GATEWAY_SERVICE_PROVIDED');
            $this->isGatewayServiceProvided = true;
        }

        // check if proxy url is provided and gateway service is not provided
        if (isset($options['proxy']['url']) && !empty($options['proxy']['url']) && !$this->isGatewayServiceProvided) {
            $this->isProxyUrlProvided = true;
            try {
                $parsedUrl = parse_url($options['proxy']['url']);

                if ($parsedUrl !== false && isset($parsedUrl['host'])) {
                    $this->hostname = $parsedUrl['host'];
                    $this->protocol = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
                    $this->proxyUrl = $options['proxy']['url'];
                    if (isset($parsedUrl['port'])) {
                        $this->port = intval($parsedUrl['port']);
                    }
                } else {
                    throw new Exception("Invalid proxy url");
                }

            } catch (Exception $e) {
                if (isset($this->logManager) && $this->logManager) {
                    $this->logManager->error('ERROR_PARSING_PROXY_URL', [
                        'err' => $e->getMessage(),
                        'accountId' => strval($this->accountId),
                        'sdkKey' => $this->sdkKey,
                        'an' => 'init'
                    ]);
                }
                $this->hostname = Constants::HOST_NAME;
            }
        }
        // Parsing URL if provided
        if (isset($options['gatewayService']['url'])) {
            $this->isGatewayServiceProvided = true;

            $parsedUrl = parse_url($options['gatewayService']['url']);
            if (!isset($parsedUrl['scheme'])) {
                $parsedUrl = parse_url('https://' . $options['gatewayService']['url']);
            }
            $this->hostname = $parsedUrl['host'];
            $this->protocol = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] : 'https';
            $this->port = isset($parsedUrl['port']) ? intval($parsedUrl['port']) : null;
        }

        $this->settingsSchemaValidator = new SettingsSchema(); // Initialize the schema validator

        self::$instance = $this;
        $this->logManager->debug('Settings Manager initialized');

        $this->initializeNetworkManager($options);
    }

    private function initializeNetworkManager($options) {
        $networkOptions = [
            'isGatewayUrlNotSecure' => isset($options['gatewayService']['isUrlNotSecure'])
                                      ? $options['gatewayService']['isUrlNotSecure']
                                      : false, 
            'shouldWaitForTrackingCalls' => isset($options['shouldWaitForTrackingCalls'])
                                      ? $options['shouldWaitForTrackingCalls']
                                      : false,
            'retryConfig' => isset($options['retryConfig']) && is_array($options['retryConfig'])
                                      ? $options['retryConfig']
                                      : null,
            'isProxyUrlNotSecure' => isset($options['proxy']['isUrlNotSecure']) ? $options['proxy']['isUrlNotSecure'] : false,
        ];

        NetworkManager::instance($networkOptions); // Pass the options to the NetworkManager
    }

    /**
     * Sets the NetworkManager instance to use for network requests
     * @param NetworkManager $networkManager The NetworkManager instance
     */
    public function setNetworkManager(NetworkManager $networkManager) {
        $this->networkManager = $networkManager;
    }

    public static function instance(): SettingsService {
        return self::$instance;
    }

    public function getSettingsSchemaValidator() {
        return $this->settingsSchemaValidator;
    }

    private function fetchSettingsAndCacheInStorage() {
        try {
            $settings = $this->fetchSettings();    
            if ($this->settingsSchemaValidator->isSettingsValid($settings)) { // Validate settings
                $this->isSettingsValidOnInit = true;
                $this->logManager->info('SETTINGS_FETCH_SUCCESS');
                return $settings;
            } else {
                $this->logManager->error('SETTINGS_SCHEMA_INVALID');
                return null;
            }
        } catch (Exception $e) {
            $this->logManager->error('SETTINGS_FETCH_ERROR', ['err' => $e->getMessage()]);
            return null;
        }
    }

    public function fetchSettings() {
        if (!$this->sdkKey || !$this->accountId) {
            $this->serviceContainer->getLogManager()->error('sdkKey is required for fetching account settings. Aborting!');
            throw new Exception('sdkKey is required for fetching account settings. Aborting!');
        }

        // Use instance-based NetworkManager if available, otherwise fall back to singleton
        $networkInstance = $this->networkManager ? $this->networkManager : NetworkManager::instance();
        $options = (new NetworkUtil())->getSettingsPath($this->sdkKey, $this->accountId);
        $options['platform'] = 'server';
        $options['api-version'] = 1;
        $options['sn'] = Constants::SDK_NAME;
        $options['sv'] = Constants::SDK_VERSION;

        if (!$networkInstance->getConfig()->getDevelopmentMode()) {
            $options['s'] = 'prod';
        }

        // Start timer for settings fetch
        $settingsFetchStartTime = microtime(true) * 1000; // milliseconds

        try {
            $request = new RequestModel(
                $this->hostname,
                'GET',
                UrlService::getEndpointWithCollectionPrefix(Constants::SETTINGS_ENDPOINT),
                $options,
                null,
                null,
                $this->protocol,
                $this->port
            );
            $request->setTimeout($this->networkTimeout);

            $response = $networkInstance->get($request);

            $this->settingsFetchTime = (int)((microtime(true) * 1000) - $settingsFetchStartTime);
            return $response->getData();
        } catch (Exception $err) {
            if (isset($this->logManager) && $this->logManager) {
                $this->logManager->error("Error occurred while fetching settings: {$err->getMessage()}");
            }
            throw $err;
        }
    }

    // Method to get settings
    public function getSettings($forceFetch = false) {
        if ($forceFetch) {
            return $this->fetchSettingsAndCacheInStorage();
        } else {
            return $this->fetchSettingsAndCacheInStorage();
        }
    }

}
