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

use vwo\Enums\UrlEnum;
use vwo\Packages\Logger\Core\LogManager;

class UrlService
{
    private static $collectionPrefix;
    private static $gatewayServiceUrl;
    private static $port;
    private static $gatewayServiceProtocol = 'https';

    public static function init(array $options = []): void
    {
        self::$collectionPrefix = $options['collectionPrefix'] ?? null;
        self::$gatewayServiceUrl = $options['gatewayServiceUrl'] ?? null;

        if (self::$gatewayServiceUrl !== null) {
            try {
                $parsedUrl = null;
                $url = self::$gatewayServiceUrl ?? '';

                if (stripos($url, 'http://') === 0 || stripos($url, 'https://') === 0) {
                    $parsedUrl = parse_url($url);
                } else {
                    $parsedUrl = parse_url('http://' . $url);
                }

                self::$gatewayServiceUrl = $parsedUrl['host'] ?? '';
                self::$gatewayServiceProtocol = $parsedUrl['scheme'] ?? 'https';
                self::$port = $parsedUrl['port'] ?? null;

            } catch (\Exception $e) {
                LogManager::instance()->error('Error parsing web service URL: ' . $e->getMessage());
            }
        }
    }

    public static function getBaseUrl(): string
    {
        $baseUrl = UrlEnum::BASE_URL; // Assuming you have defined the base URL elsewhere

        if (self::$gatewayServiceUrl !== null) {
            return self::$gatewayServiceUrl;
        }

        if (self::$collectionPrefix !== null) {
            return $baseUrl . '/' . self::$collectionPrefix;
        }

        return $baseUrl;
    }

    public static function getPort(): int
    {
        return (int)self::$port;
    }

    /**
     * Returns the web service protocol
     */
    public static function getProtocol() {
        return self::$gatewayServiceProtocol;
    }
}

?>
