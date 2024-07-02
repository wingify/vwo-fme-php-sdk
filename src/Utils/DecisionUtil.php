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
use vwo\Packages\Logger\Core\LogManager;
use vwo\Utils\DataTypeUtil;
use vwo\Packages\SegmentationEvaluator\Core\SegmentationManager;
use vwo\Utils\UuidUtil;
use vwo\Services\CampaignDecisionService;
use vwo\Models\CampaignModel;
use vwo\Enums\CampaignTypeEnum;
use vwo\Enums\StatusEnum;
use vwo\Utils\FunctionUtil;
use vwo\Utils\CampaignUtil;
use vwo\Packages\DecisionMaker\DecisionMaker;

class DecisionUtil
{
    public static function checkWhitelistingAndPreSeg($settings, $campaign, $context, $isMegWinnerRule, &$decision)
    {
        $vwoUserId = UuidUtil::getUUID($context['user']['id'], $settings->getAccountId());

        // only check whitelisting for ab campaigns
        if ($campaign->getType() === CampaignTypeEnum::AB) {
            // set _vwoUserId for variation targeting variables
            $context['user']['variationTargetingVariables'] = array_merge($context['user']['variationTargetingVariables'] ?? [], [
                '_vwoUserId' => $campaign->getIsUserListEnabled() ? $vwoUserId : $context['user']['id'],
            ]);
            $decision['variationTargetingVariables'] = $context['user']['variationTargetingVariables']; // for integration

            // check if the campaign satisfies the whitelisting
            if ($campaign->getIsForcedVariationEnabled()) {
                $whitelistedVariation = self::checkForWhitelisting($campaign, $campaign->getKey(), $settings, $context);
                if ($whitelistedVariation && !empty($whitelistedVariation)) {
                    return [true, $whitelistedVariation];
                }
            } else {
                LogManager::instance()->info(
                    "WHITELISTING_SKIPPED: Whitelisting is not used for Campaign:{$campaign->getKey()}, hence skipping evaluating whitelisting for User ID:{$context['user']['id']}"
                );
            }
        }

        if ($isMegWinnerRule) {
            return [true, null];    // for MEG winner rule, no need to check for pre segmentation as it's already evaluated
        }

        // userlist segment is also available for campaign pre segmentation
        $context['user']['customVariables'] = array_merge($context['user']['customVariables'] ?? [], [
            '_vwoUserId' => $campaign->getIsUserListEnabled() ? $vwoUserId : $context['user']['id'],
        ]);
        $decision['customVariables'] = $context['user']['customVariables'];    // for integration

        // check for campaign pre segmentation
        $preSegmentationResult = (new CampaignDecisionService())->getDecision($campaign, $settings, $context);
        return [$preSegmentationResult, null];
    }

    /**
     * Check for whitelisting
     * @param CampaignModel $campaign      Campaign object
     * @param string        $campaignKey   Campaign key
     * @param SettingsModel $settings      Settings model
     * @param array         $context       Context
     * @return array|null
     */
    private static function checkForWhitelisting($campaign, $campaignKey, $settings, $context)
    {
        $status = null;
        // check if the campaign satisfies the whitelisting
        $whitelistingResult = self::_evaluateWhitelisting($campaign, $campaignKey, $settings, $context);
        $variationString = '';
        if ($whitelistingResult) {
            $status = StatusEnum::PASSED;
            $variationString = $whitelistingResult['variation']->getKey();
        } else {
            $status = StatusEnum::FAILED;
        }
        LogManager::instance()->info(
            "SEGMENTATION_STATUS: User ID:{$context['user']['id']} for Campaign:{$campaignKey} with variables:" . json_encode($context['user']['variationTargetingVariables']) . " {$status} whitelisting {$variationString}"
        );
        return $whitelistingResult;
    }

    private static function _evaluateWhitelisting($campaign, $campaignKey, $settings, $context)
    {
        $whitelistedVariation = null;
        $status = null;
        $targetedVariations = [];

        foreach ($campaign->getVariations() as $variation) {
            if (DataTypeUtil::isObject($variation->getSegments()) && empty($variation->getSegments())) {
                LogManager::instance()->debug(
                    "WHITELISTING_SKIP : Whitelisting is not used for experiment:{$campaignKey}, hence skipping evaluating whitelisting {$variation->getKey()} for User ID:{$context['user']['id']}",
                );
                continue;
            }

            // check for segmentation and evaluate
            if (DataTypeUtil::isObject($variation->getSegments()) && !empty((array) $variation->getSegments())) {

                $segmentEvaluatorResult = SegmentationManager::instance()->validateSegmentation(
                    $variation->getSegments(),
                    $context['user']['variationTargetingVariables'],
                    $settings
                );

                if ($segmentEvaluatorResult) {
                    $status = StatusEnum::PASSED;
                    $targetedVariations[] = $variation;
                } else {
                    $status = StatusEnum::FAILED;
                }
            } else {
                $status = StatusEnum::FAILED;
            }
        }
        if (count($targetedVariations) > 1) {
            CampaignUtil::scaleVariationWeights($targetedVariations);
            $currentAllocation = 0;
            foreach ($targetedVariations as $i => $variation) {
                $stepFactor = CampaignUtil::assignRangeValues($variation, $currentAllocation);
                $currentAllocation += $stepFactor;
            }
            $whitelistedVariation = (new CampaignDecisionService())->getVariation(
                $targetedVariations,
                (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context['user']['id'], $campaign, null))
            );
        } else {
            $whitelistedVariation = $targetedVariations[0] ?? null;
        }

        if ($whitelistedVariation) {
            return [
                'variation' => $whitelistedVariation,
                'variationName' => $whitelistedVariation->getName(),
                'variationId' => $whitelistedVariation->getId(),
            ];
        }

        return null;
    }

    public static function evaluateTrafficAndGetVariation($settingsFile, $campaign, $userId)
    {
        $variation = (new CampaignDecisionService())->getVariationAlloted($userId, $settingsFile->getAccountId(), $campaign);

        if (!$variation) {
            LogManager::instance()->info(
                "USER_NOT_BUCKETED: User ID:{$userId} for Campaign:{$campaign->getKey()} did not get any variation"
            );
            return null;
        }
        LogManager::instance()->info(
            "USER_BUCKETED: User ID:{$userId} for Campaign:{$campaign->getKey()} " . ($variation->getKey() ? "got variation:{$variation->getKey()}" : "did not get any variation")
        );
        return $variation;
    }
}
