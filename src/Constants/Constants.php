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

namespace vwo\Constants;

class Constants {
    // URL related constants
    const HTTP = 'http';
    const HTTPS = 'https';

    const SEED_URL = 'https://vwo.com';
    const HTTP_PROTOCOL = self::HTTP . '://';
    const HTTPS_PROTOCOL = self::HTTPS . '://';
    const BASE_URL = 'dev.visualwebsiteoptimizer.com';

    const PLATFORM = 'platform';

    const MAX_TRAFFIC_PERCENT = 100;
    const MAX_TRAFFIC_VALUE = 10000;
    const STATUS_RUNNING = 'RUNNING';

    const SEED_VALUE = 1;
    const MAX_EVENTS_PER_REQUEST = 5000;
    const DEFAULT_REQUEST_TIME_INTERVAL = 600; // 10 * 60(secs) = 600 secs i.e. 10 minutes
    const DEFAULT_EVENTS_PER_REQUEST = 100;
    const SDK_NAME = 'vwo-fme-php-sdk';

    const SDK_VERSION = '1.8.0';
    const AP = 'server';

    const SETTINGS = 'settings';
    const SETTINGS_EXPIRY = 10000000;
    const SETTINGS_TIMEOUT = 50000;
    const API_VERSION = '1';

    const HOST_NAME = self::BASE_URL; // Use BASE_URL defined above
    const SETTINGS_ENDPOINT = '/server-side/v2-settings';

    const VWO_FS_ENVIRONMENT = 'vwo_fs_environment';

    const RANDOM_ALGO = 1;
}

?>
