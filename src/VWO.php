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
use vwo\Services\LoggerService;
use vwo\Utils\SdkInitAndUsageStatsUtil;
use vwo\Utils\UuidUtil;

class VWO
{
    // Removed static properties to support multiple instances
    // Each call to init() will create a new independent instance

    /**
     * Creates and returns a new VWO instance with the provided options.
     * This method supports multiple instances by creating a new VWOBuilder each time.
     *
     * @param array $options Configuration options for setting up VWO.
     * @return VWOClient|null The configured VWO client instance.
     */
    private static function createInstance($options)
    {
        $vwoBuilder = isset($options['vwoBuilder']) ? $options['vwoBuilder'] : new VWOBuilder($options);

        $vwoBuilder
            ->setLogger()
            ->setSettingsService()
            ->setStorage()
            ->setNetworkManager()
            ->setSegmentation()
            ->initBatching()
            ->initPolling()
            ->initUsageStats();

        // Get logManager from builder for logging
        $logManager = $vwoBuilder->getLogger();

        if (isset($options['settings'])) {
            $settingsObject = json_decode($options['settings']);
            if($vwoBuilder->getSettingsService()->settingsSchemaValidator->isSettingsValid($settingsObject)) {
                $vwoBuilder->getSettingsService()->isSettingsValidOnInit = true;
                $vwoBuilder->getSettingsService()->settingsFetchTime = 0;
                if ($logManager) {
                    $logManager->info('SETTINGS_PASSED_IN_INIT_VALID');
                }
                $vwoBuilder->setSettings($settingsObject);
                $settings = new SettingsModel($settingsObject);
            } else {
                $vwoBuilder->getSettingsService()->isSettingsValidOnInit = false;
                $vwoBuilder->getSettingsService()->settingsFetchTime = 0;
                if ($logManager) {
                    $logManager->error('SETTINGS_SCHEMA_INVALID');
                }
                $settingsObject = json_decode('{}');
                $vwoBuilder->setSettings($settingsObject);
                $settings = new SettingsModel($settingsObject);
            }
        } else {
            // Fetch settings and build VWO instance
            $settings = $vwoBuilder->getSettings();
        }
        
        $instance = null;
        if ($settings) {
            $instance = $vwoBuilder->build($settings);
        }

        return ['instance' => $instance, 'vwoBuilder' => $vwoBuilder];
    }

    /**
     * Initializes a new instance of VWO with the provided options.
     * Each call creates a new independent instance, supporting multiple SDK instances.
     *
     * @param array $options Configuration options for the VWO instance.
     * @return VWOClient|null The initialized VWO client instance.
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

            if(isset($options['isAliasingEnabled']) && !isset($options['gatewayService']['url'])) {
                throw new Exception('Please provide the gatewayService URL in the options if aliasing is enabled');
            }

            // Create a new instance (not singleton)
            $result = self::createInstance($options);
            $instance = $result['instance'];
            $vwoBuilder = $result['vwoBuilder'];

            if (!$instance) {
                return null;
            }

            # Calculate total init time
            $initTime = (int)((microtime(true) * 1000) - $initStartTime);
            $wasInitializedEarlier = false;
            
            if (isset($vwoBuilder->originalSettings) && isset($vwoBuilder->originalSettings->sdkMetaInfo) && isset($vwoBuilder->originalSettings->sdkMetaInfo->wasInitializedEarlier)) {
                $wasInitializedEarlier = $vwoBuilder->originalSettings->sdkMetaInfo->wasInitializedEarlier; 
            } else {
                $wasInitializedEarlier = false;
            }
        

            if(!isset($options['isDebuggerUsed']) || !($options['isDebuggerUsed'])) {
                if ($vwoBuilder->getSettingsService()->isSettingsValidOnInit && !$wasInitializedEarlier) {
                    SdkInitAndUsageStatsUtil::sendSdkInitEvent($vwoBuilder->getSettingsService()->settingsFetchTime, $initTime, $vwoBuilder->serviceContainer);
                }
            }

            //check if it exists or is not null
            if(isset($vwoBuilder->originalSettings->usageStatsAccountId) && $vwoBuilder->originalSettings->usageStatsAccountId !== null) {
                $usageStatsAccountId = $vwoBuilder->originalSettings->usageStatsAccountId;
            } else {
                $usageStatsAccountId = null;
            }
            if($usageStatsAccountId) {
                SdkInitAndUsageStatsUtil::sendSDKUsageStatsEvent($usageStatsAccountId, $vwoBuilder->serviceContainer);
            }

            return $instance;
        } catch (\Throwable $error) {
            $msg = sprintf('API - %s failed to execute. Trace: %s. ', $apiName, $error->getMessage());
            $logMessage = sprintf('[ERROR]: VWO-SDK %s %s', (new \DateTime())->format(DATE_ISO8601), $msg);
            file_put_contents("php://stdout", $logMessage . PHP_EOL);
            return null;
        }
    }

    /**
     * Generate a deterministic UUID for a given user and account combination.
     *
     * @param string $userId
     * @param string $accountId
     * @return string|null UUID without dashes in uppercase, or null on invalid input
     */
    public static function getUUID($userId, $accountId)
    {
        $apiName = 'getUUID';
        
        try {
            $logMessage = sprintf('[DEBUG]: VWO-SDK %s API Called: %s', (new \DateTime())->format(DATE_ISO8601), $apiName);
            file_put_contents("php://stdout", $logMessage . PHP_EOL);
            
            if (!is_string($userId) || $userId === '') {
                $logMessage = sprintf('[ERROR]: VWO-SDK %s userId passed to %s API is not of valid type.', (new \DateTime())->format(DATE_ISO8601), $apiName);
                file_put_contents("php://stdout", $logMessage . PHP_EOL);
                return null;
            }
            
            if (!is_string($accountId) || $accountId === '') {
                $logMessage = sprintf('[ERROR]: VWO-SDK %s accountId passed to %s API is not of valid type.', (new \DateTime())->format(DATE_ISO8601), $apiName);
                file_put_contents("php://stdout", $logMessage . PHP_EOL);
                return null;
            }

            return UuidUtil::getUUID($userId, $accountId);
        } catch (\Throwable $error) {
            $msg = sprintf('API - %s failed to execute. Trace: %s. ', $apiName, $error->getMessage());
            $logMessage = sprintf('[ERROR]: VWO-SDK %s %s', (new \DateTime())->format(DATE_ISO8601), $msg);
            file_put_contents("php://stdout", $logMessage . PHP_EOL);
            return null;
        }
    }
}
