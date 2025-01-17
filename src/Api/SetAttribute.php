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

namespace vwo\Api;

use vwo\Models\User\ContextModel;
use vwo\Models\SettingsModel;
use vwo\Enums\EventEnum;
use vwo\Utils\NetworkUtil;

interface ISetAttribute
{
    /**
     * Sets an attribute for a user.
     * @param SettingsModel $settings Configuration settings.
     * @param string $attributeKey The key of the attribute to set.
     * @param mixed $attributeValue The value of the attribute.
     * @param ContextModel $context Context containing user information.
     */
    public function setAttribute(SettingsModel $settings, string $attributeKey, $attributeValue, ContextModel $context): void;
}

class SetAttribute implements ISetAttribute
{
    /**
     * Implementation of setAttribute to create an impression for a user attribute.
     * @param SettingsModel $settings Configuration settings.
     * @param string $attributeKey The key of the attribute to set.
     * @param mixed $attributeValue The value of the attribute.
     * @param ContextModel $context Context containing user information.
     */
    public function setAttribute(SettingsModel $settings, string $attributeKey, $attributeValue, ContextModel $context): void
    {
        $this->createImpressionForAttribute($settings, $attributeKey, $attributeValue, $context);
    }

    /**
     * Creates an impression for a user attribute and sends it to the server.
     * @param SettingsModel $settings Configuration settings.
     * @param string $attributeKey The key of the attribute.
     * @param mixed $attributeValue The value of the attribute.
     * @param ContextModel $context Context containing user information.
     */
    private function createImpressionForAttribute(SettingsModel $settings, string $attributeKey, $attributeValue, ContextModel $context): void
    {
        $networkUtil = new NetworkUtil();

        // Retrieve base properties for the event
        $properties = $networkUtil->getEventsBaseProperties(
            $settings,
            EventEnum::VWO_SYNC_VISITOR_PROP,
            $context->getUserAgent(),
            $context->getIpAddress()
        );

        // Construct payload data for the attribute
        $payload = $networkUtil->getAttributePayloadData(
            $settings,
            $context->getId(),
            EventEnum::VWO_SYNC_VISITOR_PROP,
            $attributeKey,
            $attributeValue,
            $context->getUserAgent(),
            $context->getIpAddress()
        );

        // Send the constructed payload via POST request
        $networkUtil->sendPostApiRequest($properties, $payload);
    }
}
?>
