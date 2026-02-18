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
use vwo\Services\SettingsService;
use vwo\Constants\Constants;

class DebuggerServiceUtil {

    /**
     * Extracts only the required fields from a decision object.
     *
     * @param array $decisionObj The decision object to extract fields from
     * @return array An array containing only rId, rvId, eId and evId when present
     */
    public static function extractDecisionKeys($decisionObj = []) {
        $extractedKeys = [];

        if (isset($decisionObj['rolloutId'])) {
            $extractedKeys['rId'] = $decisionObj['rolloutId'];
        }

        if (isset($decisionObj['rolloutVariationId'])) {
            $extractedKeys['rvId'] = $decisionObj['rolloutVariationId'];
        }

        if (isset($decisionObj['experimentId'])) {
            $extractedKeys['eId'] = $decisionObj['experimentId'];
        }

        if (isset($decisionObj['experimentVariationId'])) {
            $extractedKeys['evId'] = $decisionObj['experimentVariationId'];
        }

        return $extractedKeys;
    }

    /**
     * Sends a debug event to VWO.
     *
     * @param array $eventProps The properties for the event.
     * @return void
     */
    public static function sendDebugEventToVWO($eventProps = []) {
        $networkUtil = new NetworkUtil();

        // Create query parameters
        $properties = $networkUtil->getEventsBaseProperties(EventEnum::VWO_DEBUGGER_EVENT, null, null);
        // Create payload
        $payload = $networkUtil->getDebuggerEventPayload($eventProps);
        // Send event
        $networkUtil->sendEvent($properties, $payload, EventEnum::VWO_DEBUGGER_EVENT);
    }
}

?>


