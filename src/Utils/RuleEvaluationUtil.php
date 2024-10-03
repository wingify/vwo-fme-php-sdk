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

use vwo\Models\CampaignModel;
use vwo\Models\FeatureModel;
use vwo\Models\SettingsModel;
use vwo\Services\StorageService;
use vwo\Utils\DataTypeUtil;
use vwo\Utils\DecisionUtil;
use vwo\Utils\ImpressionUtil;
use vwo\Models\User\ContextModel;

class RuleEvaluationUtil
{
    /**
     * Evaluates the rules for a given campaign and feature based on the provided context.
     * This function checks for whitelisting and pre-segmentation conditions, and if applicable,
     * sends an impression for the variation shown.
     *
     * @param SettingsModel $settings - The settings configuration for the evaluation.
     * @param FeatureModel $feature - The feature being evaluated.
     * @param CampaignModel $campaign - The campaign associated with the feature.
     * @param ContextModel $context - The user context for evaluation.
     * @param array $evaluatedFeatureMap - A map of evaluated features.
     * @param array $megGroupWinnerCampaigns - A map of MEG group winner campaigns.
     * @param StorageService $storageService - The storage service for persistence.
     * @param array $decision - The decision object that will be updated based on the evaluation.
     * @return array An array containing the result of the pre-segmentation and the whitelisted object, if any.
     */
    public static function evaluateRule($settings, $feature, $campaign, $context, &$evaluatedFeatureMap, &$megGroupWinnerCampaigns, $storageService, &$decision)
    {
        // Perform whitelisting and pre-segmentation checks
        list($preSegmentationResult, $whitelistedObject) = DecisionUtil::checkWhitelistingAndPreSeg(
            $settings,
            $feature,
            $campaign,
            $context,
            $evaluatedFeatureMap,
            $megGroupWinnerCampaigns,
            $storageService,
            $decision
        );

        // If pre-segmentation is successful and a whitelisted object exists, proceed to send an impression
        if ($preSegmentationResult && DataTypeUtil::isObject($whitelistedObject) && count((array) $whitelistedObject) > 0) {
            // Update the decision object with campaign and variation details
            $decision = array_merge($decision, [
                'experimentId' => $campaign->getId(),
                'experimentKey' => $campaign->getKey(),
                'experimentVariationId' => $whitelistedObject['variationId'],
            ]);

            // Send an impression for the variation shown
            ImpressionUtil::createAndSendImpressionForVariationShown($settings, $campaign->getId(), $whitelistedObject['variation']->getId(), $context);
        }

        // Return the results of the evaluation
        return ['preSegmentationResult' => $preSegmentationResult, 'whitelistedObject' => $whitelistedObject, 'updatedDecision' => $decision];
    }
}
?>
