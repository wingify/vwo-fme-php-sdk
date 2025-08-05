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

use vwo\Utils\DataTypeUtil;
use vwo\Models\SettingsModel;
use Exception;
use vwo\Utils\EventUtil;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\LoggerService;

class VWO
{
    private static $vwoBuilder;
    private static $instance;

    /**
     * Constructor for the VWO class.
     * Initializes a new instance of VWO with the provided options.
     *
     * @param array $options Configuration options for the VWO instance.
     * @return void
     */
    public function __construct($options = [])
    {
        // The constructor should not return anything
        self::setInstance($options);
    }

    /**
     * Sets the singleton instance of VWO.
     * Configures and builds the VWO instance using the provided options.
     *
     * @param array $options Configuration options for setting up VWO.
     * @return VWO|null The configured VWO instance.
     */
    private static function setInstance($options)
    {
        self::$vwoBuilder = isset($options['vwoBuilder']) ? $options['vwoBuilder'] : new VWOBuilder($options);

        self::$instance = self::$vwoBuilder
            ->setLogger()
            ->setSettingsService()
            ->setStorage()
            ->setNetworkManager()
            ->setSegmentation()
            ->initBatching()
            ->initPolling()
            ->initUsageStats();


        if (isset($options['settings'])) {
            $settingsObject = json_decode($options['settings']);
            if(self::$vwoBuilder->getSettingsService()->settingsSchemaValidator->isSettingsValid($settingsObject)) {
                self::$vwoBuilder->getSettingsService()->isSettingsValidOnInit = true;
                self::$vwoBuilder->getSettingsService()->settingsFetchTime = 0;
                LoggerService::info('SETTINGS_PASSED_IN_INIT_VALID');
                self::$vwoBuilder->setSettings($settingsObject);
                $settings = new SettingsModel($settingsObject);
            } else {
                self::$vwoBuilder->getSettingsService()->isSettingsValidOnInit = false;
                self::$vwoBuilder->getSettingsService()->settingsFetchTime = 0;
                LoggerService::error('SETTINGS_SCHEMA_INVALID');
                $settingsObject = json_decode('{}');
                self::$vwoBuilder->setSettings($settingsObject);
                $settings = new SettingsModel($settingsObject);
            }
        } else {
            // Fetch settings and build VWO instance
            $settings = self::$vwoBuilder->getSettings();
        }
        if ($settings) {
            self::$instance = self::$vwoBuilder->build($settings);
        }

        return self::$instance;
    }

    /**
     * Gets the singleton instance of VWO.
     *
     * @return VWO|null The singleton instance of VWO.
     */
    public static function instance()
    {
        return self::$instance;
    }

    /**
     * Initializes a new instance of VWO with the provided options.
     *
     * @param array $options Configuration options for the VWO instance.
     * @return VWO|null The initialized VWO instance.
     */
    public static function init($options = [])
    {
        # Start timer for total init time
        $initStartTime = microtime(true) * 1000;
        $apiName = 'init';
        try {
            if (!DataTypeUtil::isObject($options)) {
                throw new Exception('Options should be of type object.');
            }

            if (!isset($options['sdkKey']) || !is_string($options['sdkKey'])) {
                throw new Exception('Please provide the sdkKey in the options and should be of type string');
            }

            if (!isset($options['accountId'])) {
                throw new Exception('Please provide VWO account ID in the options and should be of type string|number');
            }

            $instance = new VWO($options);

            # Calculate total init time
            $initTime = (int)((microtime(true) * 1000) - $initStartTime);
            $wasInitializedEarlier = false;
            
            if (isset(self::$vwoBuilder->originalSettings) && isset(self::$vwoBuilder->originalSettings->sdkMetaInfo) && isset(self::$vwoBuilder->originalSettings->sdkMetaInfo->wasInitializedEarlier)) {
                $wasInitializedEarlier = self::$vwoBuilder->originalSettings->sdkMetaInfo->wasInitializedEarlier; 
            } else {
                $wasInitializedEarlier = false;
            }
        

            if (self::$vwoBuilder->getSettingsService()->isSettingsValidOnInit && !$wasInitializedEarlier) {
                EventUtil::sendSdkInitEvent(self::$vwoBuilder->getSettingsService()->settingsFetchTime, $initTime);
            }

            return self::$instance;
        } catch (\Throwable $error) {
            $msg = sprintf('API - %s failed to execute. Trace: %s. ', $apiName, $error->getMessage());
            $logMessage = sprintf('[ERROR]: VWO-SDK %s %s', (new \DateTime())->format(DATE_ISO8601), $msg);
            file_put_contents("php://stdout", $logMessage . PHP_EOL);
        }
    }
}
