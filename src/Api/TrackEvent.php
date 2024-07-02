<?php

/**
 * Copyright 2024 Wingify Software Pvt. Ltd.
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

// Include necessary utilities (replace with your actual file paths)
use vwo\Utils\NetworkUtil as NetworkUtil;
use vwo\Utils\FunctionUtil as FunctionUtil;
use vwo\Enums\ApiEnum;
use vwo\Models\SettingsModel;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\HooksManager as HooksManager;


// Interface for tracking functionality
interface ITrack
{
    public function track( $settings, $eventName, $eventProperties, $context, $hookManager);
}

class TrackEvent implements ITrack
{
    public function track( $settings, $eventName, $eventProperties, $context, $hookManager)
    {
        if (FunctionUtil::eventExists($eventName, $settings)) {
            // Create impression for track
            $this->createImpressionForTrack($settings, $eventName, $context['user'], $eventProperties);

            // Integration callback for track
            $hookManager->set([
                'eventName' => $eventName,
                'api' => ApiEnum::TRACK,
            ]);
            $hookManager->execute($hookManager->get());

            return [$eventName => true];
        }

        LogManager::instance()->error("Event '$eventName' not found in any of the features");
        return [$eventName => false];
    }

    private function createImpressionForTrack( $settings,  $eventName,  $user,  $eventProperties)
    {
        $networkUtil = new NetworkUtil();

        if(isset($user['userAgent'])){
            $userAgent = $user['userAgent'];
        } else {
            $userAgent = '';
        }

        if(isset($user['ipAddress'])){
            $userIpAddress = $user['ipAddress'];
        } else {
            $userIpAddress = '';
        }

        $properties = $networkUtil->getEventsBaseProperties(
            $settings,
            $eventName,
            $userAgent,
            $userIpAddress
        );
        $payload = $networkUtil->getTrackGoalPayloadData(
            $settings,
            $user['id'],
            $eventName,
            $eventProperties,
            $userAgent,
            $userIpAddress
        );

        $networkUtil->sendPostApiRequest($properties, $payload);
    }
}
