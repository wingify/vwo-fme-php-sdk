<?php

/**
 * Copyright 2024-2026 Wingify Software Pvt. Ltd.
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

namespace vwo;

use wingify\Enums\HostProfileEnum;

class VWO extends \wingify\Wingify
{
    protected static function createDefaultBuilder($options)
    {
        if (!isset($options['hostProfile'])) {
            $options['hostProfile'] = HostProfileEnum::VWO;
        }
        return new VWOBuilder($options);
    }

    public static function init($options = [])
    {
        if (!isset($options['hostProfile'])) {
            $options['hostProfile'] = HostProfileEnum::VWO;
        }
        if (isset($options['vwoBuilder']) && !isset($options['wingifyBuilder'])) {
            $options['wingifyBuilder'] = $options['vwoBuilder'];
        }
        return parent::init($options);
    }

    public static function getUUID($userId, $accountId)
    {
        self::applyStaticLogPrefix(['hostProfile' => HostProfileEnum::VWO]);
        return parent::getUUID($userId, $accountId);
    }
}
