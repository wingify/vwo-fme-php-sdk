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
    public $hostname;
    public $port;
    public $protocol;
    public $isGatewayServiceProvided = false;
    private static $instance;

    // Constructor
    public function __construct($options) {
        // Assigning values to properties
        $this->sdkKey = $options['sdkKey'];
        $this->accountId = $options['accountId'];
        $this->expiry = isset($options['settings']['expiry']) ? $options['settings']['expiry'] : Constants::SETTINGS_EXPIRY;
        $this->networkTimeout = isset($options['settings']['timeout']) ? $options['settings']['timeout'] : Constants::SETTINGS_TIMEOUT;

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
        } else {
            $this->hostname = Constants::HOST_NAME;
            $this->port = null;
        }
        // // Pass the flag to NetworkManager
        $this->initializeNetworkManager($options);

        self::$instance = $this;
        LogManager::instance()->debug('Settings Manager initialized');
    }

    private function initializeNetworkManager($options) {
        $networkOptions = [
            'isGatewayUrlNotSecure' => isset($options['gatewayService']['isGatewayUrlNotSecure']) 
                                      ? $options['gatewayService']['isGatewayUrlNotSecure'] 
                                      : false
        ];

        NetworkManager::instance($networkOptions); // Pass the options to the NetworkManager
    }

    public static function instance(): SettingsService {
        return self::$instance;
    }

    private function fetchSettingsAndCacheInStorage() {
        try {
            $settings = $this->fetchSettings();
            LogManager::instance()->info('Settings fetched successfully');
            return $settings;
        } catch (Exception $e) {
            LogManager::instance()->error("Settings could not be fetched: " . $e->getMessage());
            return null;
        }
    }

    public function fetchSettings() {
        if (!$this->sdkKey || !$this->accountId) {
            LogManager::instance()->error('sdkKey is required for fetching account settings. Aborting!');
            throw new Exception('sdkKey is required for fetching account settings. Aborting!');
        }

        $networkInstance = NetworkManager::instance();
        $options = (new NetworkUtil())->getSettingsPath($this->sdkKey, $this->accountId);
        $options['platform'] = 'server';
        $options['api-version'] = 1;
        if (!$networkInstance->getConfig()->getDevelopmentMode()) {
            $options['s'] = 'prod';
        }

        try {
            $request = new RequestModel(
                $this->hostname,
                'GET',
                Constants::SETTINGS_ENDPOINT,
                $options,
                null,
                null,
                $this->protocol,
                $this->port
            );
            $request->setTimeout($this->networkTimeout);

            $response = $networkInstance->get($request);
            return $response->getData();
        } catch (Exception $err) {
            LogManager::instance()->error("Error occurred while fetching settings: {$err->getMessage()}");
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
