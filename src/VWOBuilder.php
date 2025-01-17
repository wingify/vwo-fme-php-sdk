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
    public function initPolling();
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
    private $originalSettings;
    private $isSettingsFetchInProgress;
    private $settingsSetManually = false;
    private $vwoInstance;

    public function __construct($options = [])
    {
        $this->options = $options;
    }

    public function setNetworkManager()
    {
        $networkInstance = NetworkManager::instance();
        $networkInstance->attachClient($this->options['network']['client'] ?? null);
        $networkInstance->getConfig()->setDevelopmentMode($this->options['isDevelopmentMode'] ?? null);
        return $this;
    }

    public function setSegmentation()
    {
        SegmentationManager::instance()->attachEvaluator($this->options['segmentation'] ?? null);
        return $this;
    }

    public function fetchSettings($force = false)
    {
        if ($this->isSettingsFetchInProgress) {
            LogManager::instance()->info('Settings fetch in progress, waiting...');
            return;
        }

        $this->isSettingsFetchInProgress = true;

        try {
            $settingsArray = $this->settingFileManager->getSettings($force);
            $this->originalSettings = $settingsArray;
            $this->settings = new SettingsModel($settingsArray);
            $this->isSettingsFetchInProgress = false;
            return $this->settings;
        } catch (\Exception $error) {
            $this->isSettingsFetchInProgress = false;
            $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
            LogManager::instance()->error("Error fetching settings: $errorMessage");
            throw $error;
        }
    }

    public function setSettings($settings): void
    {
        LogManager::instance()->debug('API - setSettings called');
        $this->originalSettings = $settings;
        $this->settings = new SettingsModel($settings);
        $this->settings = SettingsUtil::processSettings($this->settings);
        $this->settingsSetManually = true;
    }

    public function getSettings($force = false)
    {
        if (!$force && $this->settings) {
            LogManager::instance()->info('Using already fetched and cached settings');
            return $this->settings;
        } else {
            try {
                return $this->fetchSettings($force);
            } catch (\Exception $error) {
                $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
                LogManager::instance()->error("Error getting settings: $errorMessage");
                throw $error;
            }
        }
    }

    public function setStorage()
    {
        if (!empty($this->options['storage'])) {
            $this->storage = Storage::instance()->attachConnector($this->options['storage']);
        } else {
            $this->storage = null;
        }
        return $this;
    }

    public function setSettingsService()
    {
        $this->settingFileManager = new SettingsService($this->options);
        return $this;
    }

    public function setLogger()
    {
        try {
            $this->logManager = new LogManager(isset($this->options['logger']) ? $this->options['logger'] : []);
            return $this;
        } catch (\Exception $error) {
            $errorMessage = $error instanceof \Exception ? $error->getMessage() : 'Unknown error';
            LogManager::instance()->error("Error setting Logger Instance: $errorMessage");
        }
    }

    public function setAnalyticsCallback()
    {
        if (!is_object($this->options['analyticsEvent'])) {
            LogManager::instance()->error('Analytics event should be an object');
            return $this;
        }

        if (!is_callable($this->options['analyticsEvent']['eventCallback'])) {
            LogManager::instance()->error('Analytics event callback should be callable');
            return $this;
        }

        if (
            isset($this->options['analyticsEvent']['isBatchingSupported']) &&
            !is_bool($this->options['analyticsEvent']['isBatchingSupported'])
        ) {
            LogManager::instance()->error('Analytics event batching support should be a boolean');
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
            LogManager::instance()->error('Invalid batchEvents config');
            return $this;
        }

        // BatchEventsQueue::instance()->setBatchConfig($this->options['batchEvents'], $this->options['apiKey']); // TODO
        return $this;
    }

    public function initPolling()
    {
        if (!isset($this->options['pollInterval'])) {
            return $this;
        }

        if (!is_numeric($this->options['pollInterval'])) {
            return $this;
        }

        if ($this->options['pollInterval'] < 0) {
            LogManager::instance()->error('Poll interval should be greater than 1');
            return $this;
        }
        if (!$this->settingsSetManually){
            return $this;
        }

        $this->checkAndPoll($this->options['pollInterval']);
        return $this;
    }

    private function checkAndPoll($pollingInterval)
    {
        while (true) {
            sleep($pollingInterval);
            $thisReference = $this; // Store $this in a variable accessible to the anonymous functions
            try {
                $latestSettingsFile = $this->getSettings(true);
                $lastSettingsFile = json_encode($thisReference->originalSettings);
                $stringifiedLatestSettingsFile = json_encode($latestSettingsFile);
                if ($stringifiedLatestSettingsFile !== $lastSettingsFile) {
                    $thisReference->originalSettings = $latestSettingsFile;
                    $clonedSettings = FunctionUtil::cloneObject($latestSettingsFile);
                    $thisReference->settings = SettingsUtil::processSettings($clonedSettings);
                    LogManager::instance()->info('Settings file updated');
                    SettingsUtil::setSettingsAndAddCampaignsToRules($clonedSettings, $this->vwoInstance);
                }
            } catch (\Exception $error) {
                LogManager::instance()->error('Error while fetching VWO settings with polling');
            }
        }
    }

    public function build($settings)
    {
        $this->vwoInstance = new VWOClient($this->settings, $this->options);
        return $this->vwoInstance;
    }
}
