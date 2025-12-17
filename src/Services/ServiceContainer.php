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

use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\SettingsService;
use vwo\Services\HooksService;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\Storage\Storage;
use vwo\Packages\SegmentationEvaluator\Core\SegmentationManager;
use vwo\Models\SettingsModel;
use vwo\Utils\DataTypeUtil;
use vwo\Constants\Constants;

/**
 * ServiceContainer is a class that contains all the services that are used in the SDK.
 * This allows multiple SDK instances to have their own service instances, avoiding
 * conflicts when multiple instances are created.
 */
class ServiceContainer
{
    private $logManager;
    private $settingsService;
    private $hooksService;
    private $vwoOptions;
    private $segmentationManager;
    private $settingsModel;
    private $networkManager;
    private $storage;

    /**
     * Constructor for ServiceContainer
     * @param LogManager $logManager The log manager instance
     * @param SettingsService $settingsService The settings service instance
     * @param array $vwoOptions The VWO options
     * @param SettingsModel $settingsModel The settings model
     * @param NetworkManager $networkManager The network manager instance
     * @param Storage|null $storage The storage instance (optional)
     */
    public function __construct(
        LogManager $logManager,
        SettingsService $settingsService,
        array $vwoOptions,
        SettingsModel $settingsModel,
        NetworkManager $networkManager,
        $storage = null
    ) {
        $this->logManager = $logManager;
        $this->settingsService = $settingsService;
        $this->vwoOptions = $vwoOptions;
        $this->hooksService = new HooksService($vwoOptions);
        $this->segmentationManager = new SegmentationManager();
        $this->settingsModel = $settingsModel;
        $this->networkManager = $networkManager;
        $this->storage = $storage;
    }

    /**
     * Gets the log manager instance
     * @return LogManager
     */
    public function getLogManager(): LogManager
    {
        return $this->logManager;
    }

    /**
     * Gets the settings service instance
     * @return SettingsService
     */
    public function getSettingsService(): SettingsService
    {
        return $this->settingsService;
    }

    /**
     * Gets the hooks service instance
     * @return HooksService
     */
    public function getHooksService(): HooksService
    {
        return $this->hooksService;
    }

    /**
     * Gets the VWO options
     * @return array
     */
    public function getVWOOptions(): array
    {
        return $this->vwoOptions;
    }

    /**
     * Gets the segmentation manager instance
     * @return SegmentationManager
     */
    public function getSegmentationManager(): SegmentationManager
    {
        return $this->segmentationManager;
    }

    /**
     * Gets the settings model
     * @return SettingsModel
     */
    public function getSettings(): SettingsModel
    {
        return $this->settingsModel;
    }

    /**
     * Sets the settings model in the container
     * @param SettingsModel $settingsModel The settings model to set
     */
    public function setSettings(SettingsModel $settingsModel)
    {
        $this->settingsModel = $settingsModel;
    }

    /**
     * Updates the settings model in the container
     * This should be called when settings are updated in VWOClient
     * @param SettingsModel $settingsModel The updated settings model
     */
    public function updateSettings(SettingsModel $settingsModel)
    {
        $this->settingsModel = $settingsModel;
    }

    /**
     * Gets the base URL for API calls
     * @return string
     */
    public function getBaseUrl(): string
    {
        $baseUrl = $this->settingsService->hostname;

        // If collection prefix is set, return the base url with the collection prefix
        if (
            $baseUrl == Constants::HOST_NAME &&
            $this->settingsModel->getCollectionPrefix() &&
            DataTypeUtil::isString($this->settingsModel->getCollectionPrefix())
        ) {
            return $baseUrl . '/' . $this->settingsModel->getCollectionPrefix();
        }

        return $baseUrl;
    }

    /**
     * Gets the network manager instance
     * @return NetworkManager
     */
    public function getNetworkManager(): NetworkManager
    {
        return $this->networkManager;
    }

    /**
     * Gets the storage connector
     * @return mixed|null Returns the storage connector if available, null otherwise
     */
    public function getStorageConnector()
    {
        if ($this->storage) {
            return $this->storage->getConnector();
        }
        return null;
    }

    /**
     * Gets the storage instance
     * @return Storage|null
     */
    public function getStorage()
    {
        return $this->storage;
    }
}

