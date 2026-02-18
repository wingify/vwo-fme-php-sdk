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
    const HTTPS_PROTOCOL = self::HTTPS;
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

    const SDK_VERSION = '1.20.0';
    const AP = 'server';

    const SETTINGS = 'settings';
    const SETTINGS_EXPIRY = 10000000;
    const SETTINGS_TIMEOUT = 50000;
    const API_VERSION = '1';

    const HOST_NAME = self::BASE_URL; // Use BASE_URL defined above
    const SETTINGS_ENDPOINT = '/server-side/v2-settings';

    const VWO_FS_ENVIRONMENT = 'vwo_fs_environment';

    const RANDOM_ALGO = 1;

    const PRODUCT = 'product';
    const FME = 'fme';

    // Retry configuration keys
    const RETRY_SHOULD_RETRY = 'shouldRetry';
    const RETRY_MAX_RETRIES = 'maxRetries';
    const RETRY_INITIAL_DELAY = 'initialDelay';
    const RETRY_BACKOFF_MULTIPLIER = 'backoffMultiplier';

    // Retry configuration defaults
    const DEFAULT_RETRY_CONFIG = [
        self::RETRY_SHOULD_RETRY => true,
        self::RETRY_MAX_RETRIES => 3,
        self::RETRY_INITIAL_DELAY => 2,
        self::RETRY_BACKOFF_MULTIPLIER => 2,
    ];

    // Debugger constants
    const V2_SETTINGS = 'v2-settings';
    const POLLING = 'polling';
    const FLAG_DECISION_GIVEN = 'FLAG_DECISION_GIVEN';
    const NETWORK_CALL_FAILURE_AFTER_MAX_RETRIES = 'NETWORK_CALL_FAILURE_AFTER_MAX_RETRIES';
    const NETWORK_CALL_SUCCESS_WITH_RETRIES = 'NETWORK_CALL_SUCCESS_WITH_RETRIES';
    const IMPACT_ANALYSIS = "IMPACT_ANALYSIS";

    const HTTP_SUCCESS_MIN = 200;
    const HTTP_SUCCESS_MAX = 299;
    const HTTP_SUCCESS_UPPER_BOUND = 300;
}

?>
