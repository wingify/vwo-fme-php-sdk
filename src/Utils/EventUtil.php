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

use vwo\Enums\EventEnum;
use vwo\Utils\NetworkUtil;
use vwo\Packages\Logger\Core\LogManager;

class EventUtil
{
    /**
     * Sends an SDK init event to VWO. This event is triggered when the init function is called.
     *
     * @param int|null $settingsFetchTime Time taken to fetch settings in milliseconds
     * @param int|null $sdkInitTime Time taken to initialize the SDK in milliseconds
     */
    public static function sendSdkInitEvent($settingsFetchTime = null, $sdkInitTime = null)
    {
        $networkUtil = new NetworkUtil();
        try {
            $properties = $networkUtil->getEventsBaseProperties(EventEnum::VWO_SDK_INIT_EVENT);
        
            $payload = $networkUtil->getSdkInitEventPayload(EventEnum::VWO_SDK_INIT_EVENT, $settingsFetchTime, $sdkInitTime);

            $networkUtil->sendEvent($properties, $payload, EventEnum::VWO_SDK_INIT_EVENT);
        } catch (\Exception $e) {
            LogManager::instance()->error('SDK_INIT_EVENT_ERROR', ['error' => $e->getMessage()]);
        }
    }
} 