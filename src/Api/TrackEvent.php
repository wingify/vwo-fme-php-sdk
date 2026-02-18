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

use vwo\Enums\ApiEnum;
use vwo\Models\SettingsModel;
use vwo\Models\User\ContextModel;
use vwo\Services\HooksService;
use vwo\Utils\FunctionUtil as FunctionUtil;
use vwo\Utils\NetworkUtil as NetworkUtil;
use vwo\Utils\LogMessageUtil as LogMessageUtil;
use vwo\Services\ServiceContainer;

// Interface for tracking functionality
interface ITrack
{
    /**
     * Tracks an event with given properties and context.
     * @param SettingsModel $settings Configuration settings.
     * @param string $eventName Name of the event to track.
     * @param ContextModel $context Contextual information like user details.
     * @param array $eventProperties Properties associated with the event.
     * @param HooksService $hooksService Manager for handling hooks and callbacks.
     * @return array Returns an array indicating the success or failure of the event tracking.
     */
    public function track(SettingsModel $settings, string $eventName, ContextModel $context, array $eventProperties, HooksService $hooksService, bool $isDebuggerUsed = false): array;
}

class TrackEvent implements ITrack
{
    /**
     * Implementation of the track method to handle event tracking.
     * Checks if the event exists, creates an impression, and executes hooks.
     * @param SettingsModel $settings Configuration settings.
     * @param string $eventName Name of the event to track.
     * @param ContextModel $context Contextual information like user details.
     * @param array $eventProperties Properties associated with the event.
     * @param HooksService $hooksService Manager for handling hooks and callbacks.
     * @return array Returns an array indicating the success or failure of the event tracking.
     */
    public function track(SettingsModel $settings, string $eventName, ContextModel $context, array $eventProperties, HooksService $hooksService, bool $isDebuggerUsed = false, ServiceContainer $serviceContainer = null): array
    {
        if (FunctionUtil::doesEventBelongToAnyFeature($eventName, $settings)) {
            // Create an impression for the track event
            // if settings passed in init options is true, then we don't need to send an impression
            if (!$isDebuggerUsed) {
                $this->createImpressionForTrack($settings, $eventName, $context, $eventProperties, $serviceContainer);
            }

            // Set and execute integration callback for the track event
            $hooksService->set(['eventName' => $eventName, 'api' => ApiEnum::TRACK_EVENT]);
            $hooksService->execute($hooksService->get());

            return [$eventName => true];
        }

        // Log an error if the event does not exist
        $loggerService = $serviceContainer->getLoggerService();
        $loggerService->error('EVENT_NOT_FOUND',[
            'eventName' => $eventName, 
            'an' => ApiEnum::TRACK_EVENT,
            'uuid' => $context->getUUID(),
            'sId' => $context->getSessionId(),
        ]);

        return [$eventName => false];
    }

    /**
     * Creates an impression for a track event and sends it via a POST API request.
     * @param SettingsModel $settings Configuration settings.
     * @param string $eventName Name of the event to track.
     * @param ContextModel $context Contextual information like user details.
     * @param array $eventProperties Properties associated with the event.
     * @param ServiceContainer $serviceContainer The service container (optional).
     */
    private function createImpressionForTrack(SettingsModel $settings, string $eventName, ContextModel $context, array $eventProperties, ServiceContainer $serviceContainer = null)
    {
        $networkUtil = new NetworkUtil($serviceContainer);

        // Get base properties for the event
        $properties = $networkUtil->getEventsBaseProperties($eventName, $context->getUserAgent(), $context->getIpAddress(), $context->getSessionId());

        // Prepare the payload for the track goal
        $payload = $networkUtil->getTrackGoalPayloadData(
            $settings,
            $context,
            $eventName,
            $eventProperties,
        );

        // Send the prepared payload via POST API request
        $networkUtil->sendPostApiRequest($properties, $payload, $context->getId(), $eventProperties);
    }
}
?>
