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
use vwo\Services\ServiceContainer;

interface ISetAttribute
{
    /**
     * Sets multiple attributes for a user.
     * @param SettingsModel $settings Configuration settings.
     * @param array $attributes Key-value map of attributes.
     * @param ContextModel $context Context containing user information.
     */
    public function setAttribute(SettingsModel $settings, array $attributes, ContextModel $context, bool $isDebuggerUsed = false);
}

class SetAttribute implements ISetAttribute
{
    /**
     * Implementation of setAttribute to create an impression for multiple user attributes.
     * @param SettingsModel $settings Configuration settings.
     * @param array $attributes Key-value map of attributes.
     * @param ContextModel $context Context containing user information.
     */
    public function setAttribute(SettingsModel $settings, array $attributes, ContextModel $context, bool $isDebuggerUsed = false, ServiceContainer $serviceContainer = null)
    {
        if (!$isDebuggerUsed) {
            $this->createImpressionForAttributes($settings, $attributes, $context, $serviceContainer);
        }
    }

    /**
     * Creates an impression for multiple user attributes and sends it to the server.
     * @param SettingsModel $settings Configuration settings.
     * @param array $attributes Key-value map of attributes.
     * @param ContextModel $context Context containing user information.
     * @param ServiceContainer $serviceContainer The service container (optional).
     */
    private function createImpressionForAttributes(SettingsModel $settings, array $attributes, ContextModel $context, ServiceContainer $serviceContainer = null)
    {
        $networkUtil = new NetworkUtil($serviceContainer);

        // Retrieve base properties for the event
        $properties = $networkUtil->getEventsBaseProperties(
            EventEnum::VWO_SYNC_VISITOR_PROP,
            $context->getUserAgent(),
            $context->getIpAddress()
        );

        // Construct payload data for multiple attributes
        $payload = $networkUtil->getAttributePayloadData(
            $settings,
            $context->getId(),
            EventEnum::VWO_SYNC_VISITOR_PROP,
            $attributes,
            $context->getUserAgent(),
            $context->getIpAddress()
        );

        // Send the constructed payload via POST request
        $networkUtil->sendPostApiRequest($properties, $payload);
    }
}
