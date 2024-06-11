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

use vwo\Utils\NetworkUtil as NetworkUtil;
use vwo\Enums\EventEnum;

interface ISetAttribute {
    function setAttribute($settings, $attributeKey, $attributeValue, $context);
}

class SetAttribute implements ISetAttribute {
    function setAttribute($settings, $attributeKey, $attributeValue, $context) {
        createImpressionForAttribute($settings, $attributeKey, $attributeValue, $context);
    }
}

function createImpressionForAttribute($settings, $attributeKey, $attributeValue, $user) {
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
        EventEnum::VWO_SYNC_VISITOR_PROP,
        $userAgent,
        $userIpAddress
    );
    $payload = $networkUtil->getAttributePayloadData(
        $settings,
        $user['id'],
        EventEnum::VWO_SYNC_VISITOR_PROP,
        $attributeKey,
        $attributeValue,
        $userAgent,
        $userIpAddress
    );
    $networkUtil->sendPostApiRequest($properties, $payload);
}
?>
