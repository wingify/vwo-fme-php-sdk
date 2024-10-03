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

namespace vwo;

use vwo\Utils\DataTypeUtil;
use vwo\Models\SettingsModel;
use Exception;

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
            ->initPolling();


        if (isset($options['settingsFile'])) {
            // Use the provided settings file
            $settingsObject = json_decode($options['settingsFile']);
            self::$vwoBuilder->setSettings($settingsObject);
            $settings = new SettingsModel($settingsObject);
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

            return self::$instance;
        } catch (\Throwable $error) {            
            $msg = sprintf('API - %s failed to execute. Trace: %s. ', $apiName, $error->getMessage());
            $logMessage = sprintf('[ERROR]: VWO-SDK %s %s', (new \DateTime())->format(DATE_ISO8601), $msg);
            file_put_contents("php://stdout", $logMessage . PHP_EOL);
        }
    }
}
