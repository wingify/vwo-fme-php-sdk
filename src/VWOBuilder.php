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

namespace vwo;

use vwo\Packages\SegmentationEvaluator\Core\SegmentationManager;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\SettingsManager;
use vwo\Constants\Constants;
use vwo\Enums\LogLevelEnum;
use vwo\Packages\Storage\Storage;
use vwo\Utils\FunctionUtil;
use vwo\Utils\SettingsUtil;
use vwo\Models\SettingsModel;
use vwo\Services\SettingsService;
use vwo\Utils\UsageStatsUtil;
use vwo\Services\LoggerService;
use vwo\Services\ServiceContainer;

interface IVWOBuilder
{
    public function build($settings);
    public function fetchSettings($force = false);
    public function setSettingsService();
    public function setSettings($settings);
    public function getSettings($force = false);
    public function setStorage();
    public function setNetworkManager();
    public function initBatching();
    public function setAnalyticsCallback();
    public function setLogger();
    public function setSegmentation();
}

class VWOBuilder implements IVWOBuilder
{
    private $options;
    private $settingFileManager;
    private $settings;
    private $storage;
    private $logManager;
    private $networkManager;
    public $originalSettings;
    private $isSettingsFetchInProgress;
    private $settingsSetManually = false;
    private $vwoInstance;
    public $serviceContainer;
    private $loggerService;

    public function __construct($options = [])
    {
        $this->options = $options;
        $this->serviceContainer = new ServiceContainer($options);
    }

    public function setNetworkManager()
    {
        $networkOptions = [
            'isGatewayUrlNotSecure' => isset($this->options['gatewayService']['isUrlNotSecure']) 
                ? $this->options['gatewayService']['isUrlNotSecure'] 
                : false,
            'shouldWaitForTrackingCalls' => isset($this->options['shouldWaitForTrackingCalls'])
                ? $this->options['shouldWaitForTrackingCalls']
                : false,
            'retryConfig' => isset($this->options['retryConfig']) && is_array($this->options['retryConfig'])
                ? $this->options['retryConfig']
                : null,
            'isProxyUrlNotSecure' => isset($this->options['proxy']['isUrlNotSecure']) ? $this->options['proxy']['isUrlNotSecure'] : false,
            'serviceContainer' => $this->serviceContainer,
            'logManager' => $this->logManager,
            'isProxyUrlNotSecure' => isset($this->options['proxy']['isUrlNotSecure']) ? $this->options['proxy']['isUrlNotSecure'] : false,
        ];

        // Create instance-based NetworkManager instead of singleton
        $this->networkManager = new NetworkManager();
        $this->networkManager->attachClient($this->options['network']['client'] ?? null, $networkOptions);
        $this->networkManager->getConfig()->setDevelopmentMode($this->options['isDevelopmentMode'] ?? null);

        $this->serviceContainer->setNetworkManager($this->networkManager);
        
        // Attach NetworkManager to SettingsService (matching TypeScript pattern)
        if ($this->settingFileManager) {
            $this->settingFileManager->setNetworkManager($this->networkManager);
        }
        return $this;
    }

    public function setSegmentation()
    {
        return $this;
    }

    public function fetchSettings($force = false)
    {
        if ($this->isSettingsFetchInProgress) {
            $this->logManager->info('Settings fetch in progress, waiting...');
            return;
        }

        $this->isSettingsFetchInProgress = true;

        try {
            $settingsArray = $this->settingFileManager->getSettings($force);
            $this->originalSettings = $settingsArray;
            $this->settings = new SettingsModel($settingsArray);
            $this->isSettingsFetchInProgress = false;

            // Sync settings to container
            $this->serviceContainer->setSettings($this->settings);

            return $this->settings;
        } catch (\Exception $error) {
            $this->isSettingsFetchInProgress = false;
            $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
            $this->serviceContainer->getLoggerService()->error('ERROR_FETCHING_SETTINGS', ['err' => $errorMessage]);
            throw $error;
        }
    }

    public function setSettings($settings)
    {
        $this->logManager->debug('API - setSettings called');
        $this->originalSettings = $settings;
        $this->settings = new SettingsModel($settings);
        $this->settings = SettingsUtil::processSettings($this->settings);
        $this->settingsSetManually = true;
        
        $this->serviceContainer->setSettings($this->settings);
    }

    public function getSettings($force = false)
    {
        if (!$force && $this->settings) {
            $this->logManager->info('Using already fetched and cached settings');
            return $this->settings;
        } else {
            try {
                return $this->fetchSettings($force);
            } catch (\Exception $error) {
                $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
                $this->serviceContainer->getLoggerService()->error('ERROR_FETCHING_SETTINGS', ['err' => $errorMessage]);
                throw $error;
            }
        }
    }

    public function setStorage()
    {
        if (!empty($this->options['storage'])) {
            $storageInstance = new Storage();
            $this->storage = $storageInstance->attachConnector($this->options['storage']);
            $this->serviceContainer->setStorage($storageInstance);
        } else {
            $this->storage = null;
        }
        return $this;
    }

    public function setSettingsService()
    {
        $this->settingFileManager = new SettingsService($this->options, $this->logManager, $this->loggerService);
        $this->serviceContainer->setSettingsService($this->settingFileManager);
        return $this;
    }

    public function getSettingsService()
    {
        return $this->settingFileManager;
    }

    public function setLogger()
    {
        try {
            $this->logManager = new LogManager(isset($this->options['logger']) ? $this->options['logger'] : []);
            $this->loggerService = new LoggerService($this->logManager);
            
            $this->serviceContainer->setLogManager($this->logManager);
            $this->serviceContainer->setLoggerService($this->loggerService);
            return $this;
        } catch (\Exception $error) {
            $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
            $this->logManager->error("Error setting Logger Instance: $errorMessage");
        }
    }

    /**
     * Returns the logger instance
     * @return LogManager
     */
    public function getLogger()
    {
        return $this->logManager;
    }

    public function getLoggerService()
    {
        return $this->loggerService;
    }

    public function setAnalyticsCallback()
    {
        if (!is_object($this->options['analyticsEvent'])) {
            $this->logManager->error('Analytics event should be an object');
            return $this;
        }

        if (!is_callable($this->options['analyticsEvent']['eventCallback'])) {
            $this->logManager->error('Analytics event callback should be callable');
            return $this;
        }

        if (
            isset($this->options['analyticsEvent']['isBatchingSupported']) &&
            !is_bool($this->options['analyticsEvent']['isBatchingSupported'])
        ) {
            $this->logManager->error('Analytics event batching support should be a boolean');
            return $this;
        }
        return $this;
    }

    public function initBatching()
    {
        if (!isset($this->options['batchEvents']) || !is_object($this->options['batchEvents'])) {
            return $this;
        }

        if (
            !(
                (isset($this->options['batchEvents']['eventsPerRequest']) &&
                    is_numeric($this->options['batchEvents']['eventsPerRequest']) &&
                    $this->options['batchEvents']['eventsPerRequest'] > 0 &&
                    $this->options['batchEvents']['eventsPerRequest'] <= Constants::MAX_EVENTS_PER_REQUEST) ||
                (isset($this->options['batchEvents']['requestTimeInterval']) &&
                    is_numeric($this->options['batchEvents']['requestTimeInterval']) &&
                    $this->options['batchEvents']['requestTimeInterval'] >= 1)
            ) ||
            !is_callable($this->options['batchEvents']['flushCallback'])
        ) {
            $this->logManager->error('Invalid batchEvents config');
            return $this;
        }

        // BatchEventsQueue::instance()->setBatchConfig($this->options['batchEvents'], $this->options['apiKey']); // TODO
        return $this;
    }


    private function checkAndPoll($pollingInterval)
    {
        while (true) {
            sleep($pollingInterval);
            $thisReference = $this; // Store $this in a variable accessible to the anonymous functions
            try {
                $latestSettings = $this->getSettings(true);
                $lastSettings = json_encode($thisReference->originalSettings);
                $stringifiedLatestSettings = json_encode($latestSettings);
                if ($stringifiedLatestSettings !== $lastSettings) {
                    $thisReference->originalSettings = $latestSettings;
                    $clonedSettings = FunctionUtil::cloneObject($latestSettings);
                    $thisReference->settings = SettingsUtil::processSettings($clonedSettings);
                    $this->logManager->info('Settings file updated');
                    SettingsUtil::setSettingsAndAddCampaignsToRules($clonedSettings, $this->vwoInstance, $this->logManager);
                }
            } catch (\Exception $error) {
                $this->logManager->error('Error while fetching VWO settings with polling');
            }
        }
    }

    public function build($settings)
    {
        // Attach evaluator to segmentation manager if provided
        if (isset($this->options['segmentation'])) {
            $this->serviceContainer->getSegmentationManager()->attachEvaluator($this->options['segmentation']);
        }

        $this->vwoInstance = new VWOClient($this->settings, $this->options, $this->serviceContainer);
        return $this->vwoInstance;
    }

    /**
     * Initializes usage statistics for the SDK.
     * @return {this} The instance of this builder.
     */
    public function initUsageStats()
    {
        if (isset($this->options['isUsageStatsDisabled']) && $this->options['isUsageStatsDisabled']) {
            return $this;
        }
        UsageStatsUtil::getInstance()->setUsageStats($this->options);
        return $this;
    }

}
