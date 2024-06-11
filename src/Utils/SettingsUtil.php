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

namespace vwo\Utils;

use vwo\Models\SettingsModel;

class SettingsUtil {
    public static function processSettings($settings) {
        $parsedSettings = new SettingsModel($settings);

        // Uncomment the following lines if you want to iterate through features and campaigns
        // foreach ($features as $feature) {
        //   $campaigns = $feature->getCampaigns();
        //   foreach ($campaigns as $campaign) {
        //     $campaign->setFeatureDetails($feature->getId(), $feature->getKey());
        //     setVariationAllocation($campaign->getVariations(), $feature->getVariables());
        //   }
        // }

        return $parsedSettings;
    }
}
?>
