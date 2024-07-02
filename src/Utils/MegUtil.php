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
                        if (in_array($campaign->getId(), $groupCampaignIds) && in_array($campaign->getId(), $featureCampaignIds)) {
                            if (!isset($campaignMap[$tempFeatureKey])) {
                                $campaignMap[$tempFeatureKey] = [];
                            }

                            // Use array_column to extract the 'key' values from the existing campaigns in the map
                            $existingKeys = array_map(function ($existingCampaign) {
                                return $existingCampaign->getKey();
                            }, $campaignMap[$tempFeatureKey]);

                            if (!in_array($campaign->getKey(), $existingKeys)) {
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
        if (isset($evaluatedFeatureMap[$feature->getKey()]) && isset($evaluatedFeatureMap[$feature->getKey()]['rolloutId'])) {
            return true;
        }

        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($settings, $feature->getKey(), CampaignTypeEnum::ROLLOUT);

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
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $campaign, $context['user']['id']);
                if (DataTypeUtil::isObject($variation) && count((array)$variation) > 0) {
                    $evaluatedFeatureMap[$feature->getKey()] = [
                        'rolloutId' => $ruleToTestForTraffic['id'],
                        'rolloutKey' => $ruleToTestForTraffic['key'],
                        'rolloutVariationId' => $ruleToTestForTraffic['variations'][0]['id']
                    ];
                    return true;
                }
            }

            $featureToSkip[] = $feature->getKey();
            return false;
        }

        LogManager::instance()->debug("MEG: No rollout rule found for feature {$feature->getKey()}, evaluating experiments...");
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
                    LogManager::instance()->debug("MEG: Campaign {$campaign->getKey()} is eligible for user {$context['user']['id']}");
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
            $megAlgoNumber = isset($settings->getGroups()->$groupId->et) ? $settings->getGroups()->$groupId->et : Constants::RANDOM_ALGO;

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
                    LogManager::instance()->debug("MEG: Campaign {$campaignList['eligibleCampaigns'][0]->getKey()} is the winner for group $groupId for user {$context['user']['id']}");
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
        // Loop through shortlisted campaigns as objects
        foreach ($shortlistedCampaigns as &$campaign) {
            $campaign->setWeight(floor(100 / count($shortlistedCampaigns)));
        }

        // Convert campaigns to VariationModel objects (assuming a constructor exists)
        $shortlistedCampaigns = array_map(function (&$campaign) {
            $data = new \stdClass();
            $data->id = $campaign->getId();
            $data->key = $campaign->getKey();
            $data->name = $campaign->getName();
            $data->weight = $campaign->getWeight();
            $data->variables = $campaign->getVariables();
            $data->variations = $campaign->getVariations();
            $data->segments = $campaign->getSegments();
            $data->type = $campaign->getType();
            $data->percentTraffic = $campaign->getTraffic();
            $data->isUserListEnabled = $campaign->getIsUserListEnabled();
            $data->isForcedVariationEnabled = $campaign->getIsForcedVariationEnabled();
            $data->metrics = $campaign->getMetrics();
            $data->status = $campaign->getStatus();
            $data->variationId = $campaign->getVariationId();
            $data->campaignId = $campaign->getCampaignId();

            return (new VariationModel())->modelFromDictionary($data); // Assuming constructor takes campaign object
        }, $shortlistedCampaigns);

        CampaignUtil::setCampaignAllocation($shortlistedCampaigns);

        // Assuming getVariation accepts objects and uses appropriate properties
        $winnerCampaign = (new CampaignDecisionService())->getVariation(
            $shortlistedCampaigns,
            (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context['user']['id'], null, $groupId))
        );
        LogManager::instance()->debug("MEG Random: Campaign {$winnerCampaign->getKey()} is the winner for group $groupId for user {$context['user']['id']}");

        if ($winnerCampaign && in_array($winnerCampaign->getId(), $calledCampaignIds)) {
            return $winnerCampaign;
        }
        return null;
    }

    public static function advancedAlgoFindWinningCampaign($settings, $shortlistedCampaigns, $context, $calledCampaignIds, $groupId)
    {
        $winnerCampaign = null;
        $found = false;
        $priorityOrder = isset($settings->getGroups()->$groupId->p) ? $settings->getGroups()->$groupId->p : [];
        $wt = isset($settings->getGroups()->$groupId->wt) ? (array) $settings->getGroups()->$groupId->wt : [];

        foreach ($priorityOrder as $priority) {
            foreach ($shortlistedCampaigns as $campaign) {
                if ($campaign->getId() === $priority) {
                    $winnerCampaign = $campaign;
                    $found = true;
                    break;
                }
            }
            if ($found === true) {
                break;
            }
        }

        if ($winnerCampaign === null) {
            $participatingCampaignList = [];

            foreach ($shortlistedCampaigns as $campaign) {
                $campaignId = $campaign->getId();
                if (isset($wt[$campaignId])) {
                    $campaign->setWeight($wt[$campaignId]);  // Directly setting weight property in the campaign object
                    $participatingCampaignList[] = $campaign;
                }
            }

            $participatingCampaignList = array_map(function ($campaign) {

                $data = new \stdClass();
                $data->id = $campaign->getId();
                $data->key = $campaign->getKey();
                $data->name = $campaign->getName();
                $data->weight = $campaign->getWeight();
                $data->variables = $campaign->getVariables();
                $data->variations = $campaign->getVariations();
                $data->segments = $campaign->getSegments();
                $data->type = $campaign->getType();
                $data->percentTraffic = $campaign->getTraffic();
                $data->isUserListEnabled = $campaign->getIsUserListEnabled();
                $data->isForcedVariationEnabled = $campaign->getIsForcedVariationEnabled();
                $data->metrics = $campaign->getMetrics();
                $data->status = $campaign->getStatus();
                $data->variationId = $campaign->getVariationId();
                $data->campaignId = $campaign->getCampaignId();

                $variationModel = (new VariationModel())->modelFromDictionary($data);
                return $variationModel;
            }, $participatingCampaignList);

            CampaignUtil::setCampaignAllocation($participatingCampaignList);
            $winnerCampaign = (new CampaignDecisionService())->getVariation(
                $participatingCampaignList,
                (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context['user']['id'], null, $groupId))
            );
        }

        LogManager::instance()->debug("MEG Advance: Campaign {$winnerCampaign->getKey()} is the winner for group $groupId for user {$context['user']['id']}");

        if ($winnerCampaign!=null && in_array($winnerCampaign->getId(), $calledCampaignIds)) {
            return $winnerCampaign;
        }
        return null;
    }

    public static function campaignToReturn($settings, $feature, $eligibleCampaignsForGroup, $winnerCampaigns, $context, $priorityCampaignIds, &$campaignToSkip, $decision)
    {
        $eligibleCampaignsForGroupArray = $eligibleCampaignsForGroup;

        foreach ($eligibleCampaignsForGroupArray as $groupId => $campaignList) {
            $winnerFound = false;
            $campaignToReturn = null;

            foreach ($priorityCampaignIds as $campaignId) {
                $winnerCampaign = null;

                foreach ($winnerCampaigns as $campaign) {
                    if ($campaign && $campaign->getId() === $campaignId) {
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
                        if (is_object($item) && $item->getId() === $campaignId) {
                            $campaign = $item;
                            break;
                        }
                    }
                }
    
                if (!$campaign && isset($campaignList['eligibleCampaigns'])) {
                    foreach ($campaignList['eligibleCampaigns'] as $item) {
                        if (is_object($item) && $item->getId() === $campaignId) {
                            $campaign = $item;
                            break;
                        }
                    }
                }
    
                if (!$campaign && isset($campaignList['inEligibleCampaigns'])) {
                    foreach ($campaignList['inEligibleCampaigns'] as $item) {
                        if (is_object($item) && $item->getId() === $campaignId) {
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
                LogManager::instance()->info("MEG: Campaign {$campaignToReturn->getKey()} is the winner for user {$context['user']['id']}");

                list($megResult, $whitelistedVariationInfoWithCampaign) = (new GetFlag())->evaluateRule($settings, $feature, $campaignToReturn, $context, true, $decision);

                if (is_object($whitelistedVariationInfoWithCampaign) && count(get_object_vars($whitelistedVariationInfoWithCampaign)) > 0) {
                    $whitelistedVariationInfoWithCampaign->experiementId = $campaignToReturn->getId();
                    $whitelistedVariationInfoWithCampaign->experiementKey = $campaignToReturn->getKey();
                    return [true, $whitelistedVariationInfoWithCampaign, null];
                }

                return [true, $whitelistedVariationInfoWithCampaign, $campaignToReturn];
            }
        }

        return [false, null, null];
    }
}
