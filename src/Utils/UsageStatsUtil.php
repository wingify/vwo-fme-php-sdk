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

namespace vwo\Utils;

use vwo\Constants\Constants;
use vwo\Enums\LogLevelNumberEnum;
use vwo\Services\SettingsService;

/**
 * Manages usage statistics for the SDK.
 * Tracks various features and configurations being used by the client.
 * Implements Singleton pattern to ensure a single instance.
 */
class UsageStatsUtil
{
    /** @var UsageStatsUtil Singleton instance */
    private static $instance;

    /** @var array Internal storage for usage statistics data */
    private $usageStatsData = [];

    /** Private constructor to prevent direct instantiation */
    private function __construct()
    {
    }

    /**
     * Provides access to the singleton instance of UsageStatsUtil.
     *
     * @return UsageStatsUtil The single instance of UsageStatsUtil
     */
    public static function getInstance()
    {
        if (!isset(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Sets usage statistics based on provided options.
     * Maps various SDK features and configurations to boolean flags.
     *
     * @param array $options Configuration options for the SDK
     */
    public function setUsageStats(array $options)
    {
        $storage = $options['storage'] ?? null;
        $logger = $options['logger'] ?? null;
        $integrations = $options['integrations'] ?? null;
        $vwoMeta = $options['_vwo_meta'] ?? null;
        $gatewayService = $options['gatewayService'] ?? null;
        $data = [];

        $data['a'] = SettingsService::instance()->accountId;
        $data['env'] = SettingsService::instance()->sdkKey;

        if ($integrations) {
            $data['ig'] = 1;
        }

        if ($gatewayService) {
            $data['gs'] = 1;
        }

        // if logger has transport or transports, then it is custom logger
        if ($logger && (isset($logger['transport']) || isset($logger['transports']))) {
            $data['cl'] = 1;
        }

        if ($storage) {
            $data['ss'] = 1;
        }

        if (isset($logger['level'])) {
            $data['ll'] = LogLevelNumberEnum::fromString($logger['level']) ?? -1;
        }

        if ($vwoMeta && isset($vwoMeta['ea'])) {
            $data['_ea'] = 1;
        }

        if (defined('PHP_VERSION')) {
            $data['lv'] = PHP_VERSION;
        }

        $this->usageStatsData = $data;
    }

    /**
     * Retrieves the current usage statistics.
     *
     * @return array Record containing boolean flags for various SDK features in use
     */
    public function getUsageStats()
    {
        return $this->usageStatsData;
    }
}