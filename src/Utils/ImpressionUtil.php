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

use vwo\Models\SettingsModel;
use vwo\Enums\EventEnum;
use vwo\Utils\NetworkUtil;
use vwo\Models\User\ContextModel;

class ImpressionUtil
{
    /**
     * Creates and sends an impression for a variation shown event.
     * This function constructs the necessary properties and payload for the event
     * and uses the NetworkUtil to send a POST API request.
     *
     * @param SettingsModel $settings - The settings model containing configuration.
     * @param int $campaignId - The ID of the campaign.
     * @param int $variationId - The ID of the variation shown to the user.
     * @param ContextModel $context - The user context model containing user-specific data.
     */
    public static function createAndSendImpressionForVariationShown(
        SettingsModel $settings,
        $campaignId,
        $variationId,
        ContextModel $context
    ) {
        // Get base properties for the event
        $networkUtil = new NetworkUtil();

        $properties = $networkUtil->getEventsBaseProperties(
            EventEnum::VWO_VARIATION_SHOWN,
            urlencode($context->getUserAgent()), // Encode user agent to ensure URL safety
            $context->getIpAddress()
        );

        // Construct payload data for tracking the user
        $payload = $networkUtil->getTrackUserPayloadData(
            $settings,
            $context->getId(),
            EventEnum::VWO_VARIATION_SHOWN,
            $campaignId,
            $variationId,
            $context->getUserAgent(),
            $context->getIpAddress()
        );

        // Send the constructed properties and payload as a POST request
        $networkUtil->sendPostApiRequest($properties, $payload);
    }
}

