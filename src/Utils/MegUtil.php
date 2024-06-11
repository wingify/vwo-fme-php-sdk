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

use vwo\Models\CampaignModel as CampaignModel;
use vwo\Models\SettingsModel;
use vwo\Models\VariationModel;
use vwo\Services\CampaignDecisionService;
use vwo\Packages\DecisionMaker\DecisionMaker;
use vwo\Packages\Logger\Core\LogManager;

use vwo\Utils\CampaignUtil as CampaignUtil;
use vwo\Services\StorageService;
use vwo\Api\GetFlag as GetFlag;
use vwo\Enums\CampaignTypeEnum;
use vwo\Decorators\StorageDecorator as StorageDecorator;
use vwo\Constants\Constants;

class MegUtil
{
    public static function evaluateGroups(
        SettingsModel $settings,
        $featureKey,
        $feature,
        $listOfMegCampaignsGroups,
        $evaluatedFeatureMap,
        $context,
        StorageService $storageService,
        $campaignToSkip,
        $decision
    ) {
        $featureToSkip = [];
        $eligibleCampaignsForGroup = [];

        foreach ($listOfMegCampaignsGroups as $groupId) {
            LogManager::instance()->debug("MEG: Evaluating group $groupId...");
            $campaignMap = [];
            $groupData = self::getFeatureKeysFromGroup($settings, $groupId);
            $featureKeys = $groupData['featureKeys'];
            $groupCampaignIds = $groupData['groupCampaignIds'];

            foreach ($featureKeys as $tempFeatureKey) {
                $tempFeature = FunctionUtil::getFeatureFromKey($settings, $tempFeatureKey);
                $featureCampaignIds = CampaignUtil::getCampaignIdsFromFeatureKey($settings, $tempFeatureKey);

                if (in_array($tempFeatureKey, $featureToSkip)) {
                    continue;
                }

                $result = self::evaluateFeatureRollOutRules($settings, $tempFeature, $evaluatedFeatureMap, $featureToSkip, $context);

                if ($result) {
                    foreach ($settings->getCampaigns() as $campaign) {
                        $campaign = FunctionUtil::convertObjectToArray($campaign);
                        if (in_array($campaign['id'], $groupCampaignIds) && in_array($campaign['id'], $featureCampaignIds)) {
                            if (!isset($campaignMap[$tempFeatureKey])) {
                                $campaignMap[$tempFeatureKey] = [];
                            }

                            if (!in_array($campaign['key'], array_column($campaignMap[$tempFeatureKey], 'key'))) {
                                $campaignMap[$tempFeatureKey][] = $campaign;
                            }
                        }
                    }
                }
            }

            $campaignList = self::getEligbleCampaigns($settings, $campaignMap, $context, $storageService);
            $eligibleCampaignsForGroup[$groupId] = $campaignList;
        }
        
        return self::evaluateEligibleCampaigns($settings, $featureKey, $feature, $eligibleCampaignsForGroup, $context, $campaignToSkip, $decision);
    }

    public static function getFeatureKeysFromGroup(SettingsModel $settings, $groupId)
    {
        $groupCampaignIds = CampaignUtil::getCampaignsByGroupId($settings, $groupId);
        $featureKeys = CampaignUtil::getFeatureKeysFromCampaignIds($settings, $groupCampaignIds);

        return ['featureKeys' => $featureKeys, 'groupCampaignIds' => $groupCampaignIds];
    }

    public static function evaluateFeatureRollOutRules(
        $settings,
        &$feature,
        $evaluatedFeatureMap,
        $featureToSkip,
        $context
    ) {
        if (isset($evaluatedFeatureMap[$feature->key]) && isset($evaluatedFeatureMap[$feature->key]['rolloutId'])) {
            return true;
        }

        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($settings, $feature->key, CampaignTypeEnum::ROLLOUT);

        if (count($rollOutRules) > 0) {
            $ruleToTestForTraffic = null;

            $decision = [];
            $getFlag = new GetFlag();
            foreach ($rollOutRules as $rule) {
                [$evaluateRuleResult] = $getFlag->evaluateRule($settings, $feature, $rule, $context, false, $decision);

                if ($evaluateRuleResult) {
                    $ruleToTestForTraffic = $rule;
                    break;
                }
            }

            if ($ruleToTestForTraffic !== null) {
                $campaign = (new CampaignModel())->modelFromDictionary($ruleToTestForTraffic);
                $ruleToTestForTraffic = FunctionUtil::convertObjectToArray($ruleToTestForTraffic);
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $campaign, $context['user']['id']);
                if (DataTypeUtil::isObject($variation) && count((array)$variation) > 0) {
                    $evaluatedFeatureMap[$feature->key] = [
                        'rolloutId' => $ruleToTestForTraffic['id'],
                        'rolloutKey' => $ruleToTestForTraffic['key'],
                        'rolloutVariationId' => $ruleToTestForTraffic['variations'][0]['id']
                    ];
                    return true;
                }
            }

            $featureToSkip[] = $feature->key;
            return false;
        }

        LogManager::instance()->debug("MEG: No rollout rule found for feature {$feature->key}, evaluating experiments...");
        return true;
    }

    public static function getEligbleCampaigns(
        $settings,
        $campaignMap,
        $context,
        StorageService $storageService
    ) {
        
        $eligibleCampaigns = [];
        $eligibleCampaignsWithStorage = [];
        $inEligibleCampaigns = [];

        foreach ($campaignMap as $featureKey => $campaigns) {
            foreach ($campaigns as $campaign) {
                
                $storedData = (new StorageDecorator())->getFeatureFromStorage($featureKey, $context['user'], $storageService);

                if (isset($storedData['experimentVariationId'])) {
                    if (isset($storedData['experimentKey']) && $storedData['experimentKey'] === $campaign->getKey()) {
                        $variation = CampaignUtil::getCampaignVariation(
                            $settings,
                            $storedData['experimentKey'],
                            $storedData['experimentVariationId']
                        );

                        if ($variation) {
                            LogManager::instance()->debug("MEG: Campaign {$storedData['experimentKey']} found in storage for user {$context['user']['id']}");
                            $eligibleCampaignsWithStorage[] = $campaign;
                            continue;
                        }
                    }
                }
                $decisionService = new CampaignDecisionService();
                if (
                    $decisionService->getDecision((new CampaignModel())->modelFromDictionary($campaign), $settings, $context) &&
                    $decisionService->isUserPartOfCampaign($context['user']['id'], $campaign)
                ) {
                    LogManager::instance()->debug("MEG: Campaign {$campaign['key']} is eligible for user {$context['user']['id']}");
                    $eligibleCampaigns[] = $campaign;
                    continue;
                }

                $inEligibleCampaigns[] = $campaign;
            }
        }

        return [
            'eligibleCampaigns' => $eligibleCampaigns,
            'eligibleCampaignsWithStorage' => $eligibleCampaignsWithStorage,
            'inEligibleCampaigns' => $inEligibleCampaigns
        ];
    }

    public static function evaluateEligibleCampaigns(
        $settings,
        $featureKey,
        $feature,
        $eligibleCampaignsForGroup,
        $context,
        $campaignToSkip,
        $decision
    ) {
        $winnerFromEachGroup = [];
        $campaignIds = CampaignUtil::getCampaignIdsFromFeatureKey($settings, $featureKey);

        foreach ($eligibleCampaignsForGroup as $groupId => $campaignList) {
            $megAlgoNumber = isset($settings->groups[$groupId]['et']) ?$settings->groups[$groupId]['et'] : Constants::RANDOM_ALGO;

            if (count($campaignList['eligibleCampaignsWithStorage']) === 1) {
                $winnerFromEachGroup[] = $campaignList['eligibleCampaignsWithStorage'][0];
                LogManager::instance()->debug("MEG: Campaign {$campaignList['eligibleCampaignsWithStorage'][0]->getKey()} is the winner for group $groupId for user {$context['user']['id']}");
            } elseif (count($campaignList['eligibleCampaignsWithStorage']) > 1 && $megAlgoNumber === Constants::RANDOM_ALGO) {
                $winnerFromEachGroup[] = self::normalizeAndFindWinningCampaign($campaignList['eligibleCampaignsWithStorage'], $context, $campaignIds, $groupId);
            } elseif (count($campaignList['eligibleCampaignsWithStorage']) > 1) {
                $winnerFromEachGroup[] = self::advancedAlgoFindWinningCampaign($settings, $campaignList['eligibleCampaignsWithStorage'], $context, $campaignIds, $groupId);
            }

            if (count($campaignList['eligibleCampaignsWithStorage']) === 0) {
                if (count($campaignList['eligibleCampaigns']) === 1) {
                    $winnerFromEachGroup[] = $campaignList['eligibleCampaigns'][0];
                    LogManager::instance()->debug("MEG: Campaign {$campaignList['eligibleCampaigns'][0]['key']} is the winner for group $groupId for user {$context['user']['id']}");
                } elseif (count($campaignList['eligibleCampaigns']) > 1 && $megAlgoNumber === Constants::RANDOM_ALGO) {
                    $winnerFromEachGroup[] = self::normalizeAndFindWinningCampaign($campaignList['eligibleCampaigns'], $context, $campaignIds, $groupId);
                } elseif (count($campaignList['eligibleCampaigns']) > 1) {
                    $winnerFromEachGroup[] = self::advancedAlgoFindWinningCampaign($settings, $campaignList['eligibleCampaigns'], $context, $campaignIds, $groupId);
                }
            }
        }

        return self::campaignToReturn($settings, $feature, $eligibleCampaignsForGroup, $winnerFromEachGroup, $context, $campaignIds, $campaignToSkip, $decision);
    }

    public static function normalizeAndFindWinningCampaign(&$shortlistedCampaigns, $context, &$calledCampaignIds, $groupId)
    {
        foreach ($shortlistedCampaigns as &$campaign) {
            $campaign['weight'] = floor(100 / count($shortlistedCampaigns));
        }
        $shortlistedCampaigns = array_map(function (&$campaign) {
            return (new VariationModel())->modelFromDictionary($campaign);
        }, $shortlistedCampaigns);
        
        $shortlistedCampaigns = FunctionUtil::convertObjectToArray($shortlistedCampaigns);
        

        CampaignUtil::setCampaignAllocation($shortlistedCampaigns);

        $winnerCampaign = (new CampaignDecisionService())->getVariation(
            $shortlistedCampaigns,
            (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context['user']['id'], null, $groupId))
        );
        LogManager::instance()->debug("MEG Random: Campaign {$winnerCampaign['key']} is the winner for group $groupId for user {$context['user']['id']}");

        if ($winnerCampaign && in_array($winnerCampaign['id'], $calledCampaignIds)) {
            return $winnerCampaign;
        }
        return null;
    }

    public static function advancedAlgoFindWinningCampaign($settings, $shortlistedCampaigns, $context, $calledCampaignIds, $groupId)
    {
        $winnerCampaign = null;
        $found = false;
        $priorityOrder = isset($settings->groups[$groupId]['p']) ? $settings->groups[$groupId]['p'] : [];
        $wt = isset($settings->groups[$groupId]['wt']) ? $settings->groups[$groupId]['wt'] : [];

        foreach ($priorityOrder as $priority) {
            foreach ($shortlistedCampaigns as $campaign) {
                if ($campaign['id'] === $priority) {
                    $winnerCampaign = $campaign;
                    $found = true;
                    break;
                }
            }
            if ($found === true) break;
        }

        if ($winnerCampaign === null) {
            $participatingCampaignList = [];

            foreach ($shortlistedCampaigns as $campaign) {
                $campaignId = $campaign['id'];

                if (isset($wt[$campaignId])) {
                    $clonedCampaign = $campaign;
                    $clonedCampaign['weight'] = $wt[$campaignId];
                    $participatingCampaignList[] = $clonedCampaign;
                }
            }

            $participatingCampaignList = array_map(function ($campaign) {
                $variationModel = (new VariationModel())->modelFromDictionary($campaign);
                $variationModel = FunctionUtil::convertObjectToArray($variationModel);
                return $variationModel;
            }, $participatingCampaignList);

            CampaignUtil::setCampaignAllocation($participatingCampaignList);
            $winnerCampaign = (new CampaignDecisionService())->getVariation(
                $participatingCampaignList,
                (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context['user']['id'], null, $groupId))
            );
        }

        LogManager::instance()->debug("MEG Advance: Campaign {$winnerCampaign['key']} is the winner for group $groupId for user {$context['user']['id']}");

        if (in_array($winnerCampaign['id'], $calledCampaignIds)) {
            return $winnerCampaign;
        }
        return null;
    }

    public static function campaignToReturn($settings, $feature, $eligibleCampaignsForGroup, $winnerCampaigns, $context, $priorityCampaignIds, &$campaignToSkip, $decision) {
        $eligibleCampaignsForGroupArray = $eligibleCampaignsForGroup;
        
        foreach ($eligibleCampaignsForGroupArray as $groupId => $campaignList) {
            $winnerFound = false;
            $campaignToReturn = null;
            
            foreach ($priorityCampaignIds as $campaignId) {
                $winnerCampaign = null;
                
                foreach ($winnerCampaigns as $campaign) {
                    if (is_array($campaign) && isset($campaign['id']) && $campaign['id'] === $campaignId) {
                        $winnerCampaign = $campaign;
                        break;
                    }
                }
                
                if ($winnerCampaign) {
                    $campaignToReturn = $winnerCampaign;
                    $winnerFound = true;
                    break;
                }
                
                if (in_array($campaignId, $campaignToSkip) || CampaignUtil::getRuleTypeUsingCampaignIdFromFeature($feature, $campaignId) === CampaignTypeEnum::ROLLOUT) {
                    continue;
                }
                
                $campaign = null;
                
                if (isset($campaignList['eligibleCampaignsWithStorage'])) {
                    foreach ($campaignList['eligibleCampaignsWithStorage'] as $item) {
                        if (is_array($item) && isset($item['id']) && $item['id'] === $campaignId) {
                            $campaign = $item;
                            break;
                        }
                    }
                }
    
                if (!$campaign && isset($campaignList['eligibleCampaigns'])) {
                    foreach ($campaignList['eligibleCampaigns'] as $item) {
                        if (is_array($item) && isset($item['id']) && $item['id'] === $campaignId) {
                            $campaign = $item;
                            break;
                        }
                    }
                }
    
                if (!$campaign && isset($campaignList['inEligibleCampaigns'])) {
                    foreach ($campaignList['inEligibleCampaigns'] as $item) {
                        if (is_array($item) && isset($item['id']) && $item['id'] === $campaignId) {
                            $campaign = $item;
                            break;
                        }
                    }
                }
    
                if ($campaign) {
                    continue;
                } else {
                    $campaignToSkip[] = $campaignId;
                    return [false, null, null];
                }
            }
    
            if ($winnerFound) {
                LogManager::Instance()->info("MEG: Campaign {$campaignToReturn['key']} is the winner for user {$context['user']['id']}");
    
                list($megResult, $whitelistedVariationInfoWithCampaign) = (new GetFlag())->evaluateRule($settings, $feature, $campaignToReturn, $context, true, $decision);
    
                if (is_object($whitelistedVariationInfoWithCampaign) && count((array)$whitelistedVariationInfoWithCampaign) > 0) {
                    $whitelistedVariationInfoWithCampaign['experiementId'] = $campaignToReturn['id'];
                    $whitelistedVariationInfoWithCampaign['experiementKey'] = $campaignToReturn['key'];
                    return [true, $whitelistedVariationInfoWithCampaign, null];
                }
    
                return [true, $whitelistedVariationInfoWithCampaign, $campaignToReturn];
            }
        }
    
        return [false, null, null];
    }    
}
