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
use vwo\Utils\CampaignUtil;
use vwo\Utils\FunctionUtil;
use vwo\Utils\GatewayServiceUtil;

class SettingsUtil {
    
    public static function processSettings($settings) {
        $parsedSettings = new SettingsModel($settings);

        return $parsedSettings;
    }
    
    public static function setSettingsAndAddCampaignsToRules($settings, $vwoClientInstance) {
        $vwoClientInstance->settings = new SettingsModel($settings);
        $vwoClientInstance->originalSettings = $settings;

        // Optimize loop by avoiding multiple calls to `getCampaigns()`
        $campaigns = $vwoClientInstance->settings->getCampaigns();

        foreach ($campaigns as $index => $campaign) {
            CampaignUtil::setVariationAllocation($campaign);
            $campaigns[$index] = $campaign;
        }

        FunctionUtil::addLinkedCampaignsToSettings($vwoClientInstance->settings);
        GatewayServiceUtil::addIsGatewayServiceRequiredFlag($vwoClientInstance->settings);
    }
}
?>
