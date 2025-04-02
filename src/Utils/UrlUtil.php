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

use vwo\Services\SettingsService;
use vwo\Utils\DataTypeUtil;
use vwo\Packages\Logger\Core\LogManager;

class UrlUtil
{
    private static $collectionPrefix;
    private static $gatewayServiceUrl;

    /**
     * Initializes the UrlUtil with optional collectionPrefix.
     * If provided, these values are set after validation.
     * 
     * @param array $options Optional prefix for URL collections.
     * @return void
     */
    public static function init(array $options = [])
    {
        if (isset($options['collectionPrefix']) && DataTypeUtil::isString($options['collectionPrefix'])) {
            self::$collectionPrefix = $options['collectionPrefix'];
        }

        self::$gatewayServiceUrl = $options['gatewayServiceUrl'] ?? null;
    }

    /**
     * Retrieves the base URL.
     * If gatewayServiceUrl is set, it returns that; otherwise, it constructs the URL using baseUrl and collectionPrefix.
     * 
     * @return string The base URL.
     */
    public static function getBaseUrl(): string
    {
        $baseUrl = SettingsService::instance()->hostname;

        if (SettingsService::instance()->isGatewayServiceProvided) {
            return $baseUrl;
        }

        if (self::$collectionPrefix) {
            return $baseUrl . '/' . self::$collectionPrefix;
        }

        return $baseUrl;
    }
}

?>
