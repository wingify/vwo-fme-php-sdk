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

use vwo\Enums\CampaignTypeEnum;
use vwo\Enums\StatusEnum;
use vwo\Models\CampaignModel;
use vwo\Models\FeatureModel;
use vwo\Models\VariationModel;
use vwo\Models\SettingsModel;
use vwo\Models\User\ContextModel;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\CampaignDecisionService;
use vwo\Services\StorageService;
use vwo\Packages\DecisionMaker\DecisionMaker;
use vwo\Packages\SegmentationEvaluator\Core\SegmentationManager;
use vwo\Decorators\StorageDecorator;

class DecisionUtil
{
    public static function checkWhitelistingAndPreSeg(
        SettingsModel $settings,
        FeatureModel $feature,
        CampaignModel $campaign,
        ContextModel $context,
        &$evaluatedFeatureMap,
        &$megGroupWinnerCampaigns,
        StorageService $storageService,
        &$decision
    ) {
        $vwoUserId = UuidUtil::getUUID((string)$context->getId(), $settings->getAccountId());
        $campaignId = $campaign->getId();

        if ($campaign->getType() === CampaignTypeEnum::AB) {
            // Set _vwoUserId for variation targeting variables
            $context->setVariationTargetingVariables(array_merge(
                $context->getVariationTargetingVariables() ?? [],
                ['_vwoUserId' => $campaign->getIsUserListEnabled() ? $vwoUserId : $context->getId()]
            ));
            $decision['variationTargetingVariables'] = $context->getVariationTargetingVariables();

            // Check if the campaign satisfies the whitelisting
            if ($campaign->getIsForcedVariationEnabled()) {
                $whitelistedVariation = self::_checkCampaignWhitelisting($campaign, $context);
                if ($whitelistedVariation && !empty($whitelistedVariation)) {
                    return [true, $whitelistedVariation];
                }
            } else {
                LogManager::instance()->info(
                    "WHITELISTING_SKIPPED: Whitelisting is not used for Campaign:{$campaign->getRuleKey()}, hence skipping evaluating whitelisting for User ID:{$context->getId()}"
                );
            }
        }

        // userlist segment is also available for campaign pre segmentation
        $context->setCustomVariables(array_merge(
            $context->getCustomVariables() ?? [],
            ['_vwoUserId' => $campaign->getIsUserListEnabled() ? $vwoUserId : $context->getId()]
        ));
        $decision['customVariables'] = $context->getCustomVariables();

        // Check if Rule being evaluated is part of Mutually Exclusive Group
        $groupDetails = CampaignUtil::getGroupDetailsIfCampaignPartOfIt($settings,$campaign->getId(), $campaign->getType() === CampaignTypeEnum::PERSONALIZE ? $campaign->getVariations()[0]->getId() : null);

        if (is_array($groupDetails) && isset($groupDetails['groupId'])) {
            $groupWinnerCampaignId = $megGroupWinnerCampaigns[$groupDetails['groupId']] ?? null;
        } else  {
            $groupWinnerCampaignId = null;
        }

        if ($groupWinnerCampaignId) {
            if ($campaign->getType() === CampaignTypeEnum::AB) {
                if ($groupWinnerCampaignId === $campaignId) {
                    return [true, null];
                }
            } else if ($campaign->getType() === CampaignTypeEnum::PERSONALIZE) {
                if ($groupWinnerCampaignId === $campaignId . '_' . $campaign->getVariations()[0]->getId()) {
                    return [true, null];
                }
            }
            return [false, null];
        } else {
            if (!empty($groupDetails) && isset($groupDetails['groupId'])) {
                $storedData = (new StorageDecorator())->getFeatureFromStorage(
                    "_vwo_meta_meg_{$groupDetails['groupId']}",
                    $context,
                    $storageService
                );
                if ($storedData && isset($storedData['experimentKey']) && isset($storedData['experimentId'])) {
                    LogManager::instance()->info(
                        "MEG_CAMPAIGN_FOUND_IN_STORAGE: Campaign:{$storedData['experimentKey']} found in storage for User:{$context->getId()}"
                    );
                    if ($storedData['experimentId'] == $campaignId) {
                        if ($campaign->getType() === CampaignTypeEnum::PERSONALIZE) {
                            if ($storedData['experimentVariationId'] == $campaign->getVariations()[0]->getId()) {
                                return [true, null];
                            } else {
                                $megGroupWinnerCampaigns[$groupDetails['groupId']] = $storedData['experimentId'] . '_' . $storedData['experimentVariationId'];
                                return [false, null];
                            }
                        } else {
                            return [true, null];
                        }
                    }
                    if ($storedData['experimentVariationId'] != -1) {
                        $megGroupWinnerCampaigns[$groupDetails['groupId']] = $storedData['experimentId'] . '_' . $storedData['experimentVariationId'];
                    } else {
                        $megGroupWinnerCampaigns[$groupDetails['groupId']] = $storedData['experimentId'];
                    }
                    return [false, null];
                }
            }
        }

        // If Whitelisting is skipped/failed and campaign not part of any MEG Groups
        // Check campaign's pre-segmentation
        $isPreSegmentationPassed = (new CampaignDecisionService())->getPreSegmentationDecision($campaign, $context);

        if ($isPreSegmentationPassed && isset($groupDetails['groupId'])) {
            $winnerCampaign = MegUtil::evaluateGroups(
                $settings,
                $feature,
                $groupDetails['groupId'],
                $evaluatedFeatureMap,
                $context,
                $storageService
            );
            if ($winnerCampaign && $winnerCampaign->getId() === $campaignId) {
                if ($winnerCampaign->getType() === CampaignTypeEnum::AB) {
                    return [true, null];
                } else {
                    if ($winnerCampaign->getVariations()[0]->getId() === $campaign->getVariations()[0]->getId()) {
                        return [true, null];
                    } else {
                        $megGroupWinnerCampaigns[$groupDetails['groupId']] = $winnerCampaign->getId() . '_' . $winnerCampaign->getVariations()[0]->getId();
                        return [false, null];
                    }
                }
            } else if ($winnerCampaign) {
                if ($winnerCampaign->getType() === CampaignTypeEnum::AB) {
                    $megGroupWinnerCampaigns[$groupDetails['groupId']] = $winnerCampaign->getId();
                } else {
                    $megGroupWinnerCampaigns[$groupDetails['groupId']] = $winnerCampaign->getId() . '_' . $winnerCampaign->getVariations()[0]->getId();
                }
                return [false, null];
            }
            $megGroupWinnerCampaigns[$groupDetails['groupId']] = -1;
            return [false, null];
        }
        
        return [$isPreSegmentationPassed, null];
    }

    public static function evaluateTrafficAndGetVariation(SettingsModel $settings, CampaignModel $campaign, $userId)
    {
        $variation = (new CampaignDecisionService())->getVariationAlloted($userId, $settings->getAccountId(), $campaign);
        $campaignKey = $campaign->getType() === CampaignTypeEnum::AB ? $campaign->getKey() : $campaign->getName() . '_' . $campaign->getRuleKey();
        if (!$variation) {
            LogManager::instance()->info(
                "USER_CAMPAIGN_BUCKET_INFO: Campaign:{$campaignKey} User:{$userId} did not get any variation"
            );
            return null;
        }

        LogManager::instance()->info(
            "USER_CAMPAIGN_BUCKET_INFO: Campaign:{$campaignKey} User:{$userId} got variation:{$variation->getKey()}"
        );

        return $variation;
    }

    /******************
     * PRIVATE METHODS
     ******************/

    private static function _checkCampaignWhitelisting(CampaignModel $campaign, ContextModel $context)
    {
        $whitelistingResult = self::_evaluateWhitelisting($campaign, $context);
        $status = $whitelistingResult ? StatusEnum::PASSED : StatusEnum::FAILED;
        $variationString = $whitelistingResult ? $whitelistingResult['variation']->getKey() : '';
        
        $campaignKey = $campaign->getType() === CampaignTypeEnum::AB ? $campaign->getKey() : $campaign->getName() . '_' . $campaign->getRuleKey();
        LogManager::instance()->info(
            "WHITELISTING_STATUS: Campaign:{$campaignKey} User:{$context->getId()} Status:{$status} Variation:{$variationString}"
        );

        return $whitelistingResult;
    }

    private static function _evaluateWhitelisting(CampaignModel $campaign, ContextModel $context)
    {
        $targetedVariations = [];

        foreach ($campaign->getVariations() as $variation) {
            if (is_object($variation->getSegments()) && empty((array)$variation->getSegments())) {
                $campaignKey = $campaign->getType() === CampaignTypeEnum::AB ? $campaign->getKey() : $campaign->getName() . '_' . $campaign->getRuleKey();
                LogManager::instance()->info(
                    "WHITELISTING_SKIP: Campaign:{$campaignKey} User:{$context->getId()} Skipped for variation: {$variation->getKey()}"
                );
                continue;
            }

            if (is_object($variation->getSegments())) {
                $segmentEvaluatorResult = SegmentationManager::instance()->validateSegmentation(
                    $variation->getSegments(),
                    $context->getVariationTargetingVariables()
                );

                if ($segmentEvaluatorResult) {
                    $targetedVariations[] = clone $variation;
                }
            }
        }

        if (count($targetedVariations) > 1) {
            CampaignUtil::scaleVariationWeights($targetedVariations);
            for ($i = 0, $currentAllocation = 0, $stepFactor = 0; $i < count($targetedVariations); $i++) {
                $stepFactor = CampaignUtil::assignRangeValues($targetedVariations[$i], $currentAllocation);
                $currentAllocation += $stepFactor;
            }
            $whitelistedVariation = (new CampaignDecisionService())->getVariation(
                $targetedVariations,
                (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context->getId(), $campaign, null))
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
}
