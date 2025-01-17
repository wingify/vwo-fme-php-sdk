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

use vwo\Constants\Constants;
use vwo\Decorators\StorageDecorator;
use vwo\Enums\CampaignTypeEnum;
use vwo\Enums\InfoLogMessagesEnum;
use vwo\Models\CampaignModel;
use vwo\Models\FeatureModel;
use vwo\Models\VariationModel;
use vwo\Models\SettingsModel;
use vwo\Models\User\ContextModel;
use vwo\Packages\DecisionMaker\DecisionMaker;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Services\CampaignDecisionService;
use vwo\Services\StorageService;
use vwo\Utils\RuleEvaluationUtil;
use vwo\Utils\CampaignUtil;
use vwo\Utils\DataTypeUtil;
use vwo\Utils\DecisionUtil;
use vwo\Utils\FunctionUtil;
use vwo\Utils\LogMessageUtil;


class MegUtil
{
    /**
     * Evaluates groups for a given feature and group ID.
     *
     * @param SettingsModel $settings
     * @param FeatureModel $feature
     * @param int $groupId
     * @param array $evaluatedFeatureMap
     * @param ContextModel $context
     * @param StorageService $storageService
     * @return array
     */
    public static function evaluateGroups($settings, $feature, $groupId, &$evaluatedFeatureMap, $context, $storageService)
    {
        $featureToSkip = [];
        $campaignMap = [];

        // get all feature keys and all campaignIds from the groupId
        $result = self::getFeatureKeysFromGroup($settings, $groupId);
        $featureKeys = $result['featureKeys'];
        $groupCampaignIds = $result['groupCampaignIds'];

        foreach ($featureKeys as $featureKey) {
            $tempFeature = FunctionUtil::getFeatureFromKey($settings, $featureKey);
            // check if the feature is already evaluated
            if (in_array($featureKey, $featureToSkip)) {
                continue;
            }
            
            // evaluate the feature rollout rules
            $isRolloutRulePassed = self::isRolloutRuleForFeaturePassed(
                $settings,
                $tempFeature,
                $evaluatedFeatureMap,
                $featureToSkip,
                $storageService,
                $context
            );
            if ($isRolloutRulePassed) {
                foreach ($settings->getFeatures() as $tempFeature) {
                    if ($tempFeature->getKey() === $featureKey) {
                        foreach ($tempFeature->getRulesLinkedCampaign() as $rule) {                            
                            if (in_array($rule->getId(), $groupCampaignIds) || in_array($rule->getId() . '_' . $rule->getVariations()[0]->getId(), $groupCampaignIds)) {
                                if (!isset($campaignMap[$featureKey])) {
                                    $campaignMap[$featureKey] = [];
                                }
                                // check if the campaign is already present in the campaignMap for the feature
                                if (array_search($rule->getRuleKey(), array_column($campaignMap[$featureKey], 'ruleKey')) === false) {
                                    $campaignMap[$featureKey][] = $rule;
                                }
                            }
                        }
                    }
                }
            }
        }

        $result = self::getEligbleCampaigns($settings, $campaignMap, $context, $storageService);

        return self::findWinnerCampaignAmongEligibleCampaigns(
            $settings,
            $feature->getKey(),
            $result['eligibleCampaigns'],
            $result['eligibleCampaignsWithStorage'],
            $groupId,
            $context,
            $storageService
        );
    }

    /**
     * Retrieves feature keys associated with a group based on the group ID.
     *
     * @param SettingsModel $settings
     * @param int $groupId
     * @return array
     */
    public static function getFeatureKeysFromGroup($settings, $groupId)
    {
        $groupCampaignIds = CampaignUtil::getCampaignsByGroupId($settings, $groupId);
        $featureKeys = CampaignUtil::getFeatureKeysFromCampaignIds($settings, $groupCampaignIds);

        return ['featureKeys' => $featureKeys, 'groupCampaignIds' => $groupCampaignIds];
    }

    /*******************************
     * PRIVATE methods - MegUtil
     ******************************/

    /**
     * Evaluates the feature rollout rules for a given feature.
     *
     * @param SettingsModel $settings
     * @param FeatureModel $feature
     * @param array $evaluatedFeatureMap
     * @param array $featureToSkip
     * @param StorageService $storageService
     * @param ContextModel $context
     * @return bool
     */
    private static function isRolloutRuleForFeaturePassed($settings, $feature, &$evaluatedFeatureMap, $featureToSkip, $storageService, $context)
    {
        if (isset($evaluatedFeatureMap[$feature->getKey()]) && array_key_exists('rolloutId', $evaluatedFeatureMap[$feature->getKey()])) {
            return true;
        }
        $rollOutRules = FunctionUtil::getSpecificRulesBasedOnType($feature, CampaignTypeEnum::ROLLOUT);
        if (count($rollOutRules) > 0) {
            $ruleToTestForTraffic = null;
            $megGroupWinnerCampaigns = null;
            foreach ($rollOutRules as $rule) {
                $decision = [];
                $result = RuleEvaluationUtil::evaluateRule(
                    $settings,
                    $feature,
                    $rule,
                    $context,
                    $evaluatedFeatureMap,
                    $megGroupWinnerCampaigns,
                    $storageService,
                    $decision
                );
                if ($result['preSegmentationResult']) {
                    $ruleToTestForTraffic = $rule;
                    break;
                }
                continue;
            }
            if ($ruleToTestForTraffic !== null) {
                $campaign = (new CampaignModel())->modelFromDictionary($ruleToTestForTraffic);
                $variation = DecisionUtil::evaluateTrafficAndGetVariation($settings, $campaign, $context->getId());
                if (DataTypeUtil::isObject($variation) && count((array)$variation) > 0) {
                    $evaluatedFeatureMap[$feature->getKey()] = [
                        'rolloutId' => $ruleToTestForTraffic->getId(),
                        'rolloutKey' => $ruleToTestForTraffic->getKey(),
                        'rolloutVariationId' => $ruleToTestForTraffic->getVariations()[0]->getId(),
                    ];
                    return true;
                }
            }
            // no rollout rule passed
            $featureToSkip[] = $feature->getKey();
            return false;
        }

        LogManager::instance()->debug("MEG: No rollout rule found for feature {$feature->getKey()}, evaluating experiments...");
        return true;
    }

    /**
     * Retrieves eligible campaigns based on the provided campaign map and context.
     *
     * @param SettingsModel $settings
     * @param array $campaignMap
     * @param ContextModel $context
     * @param StorageService $storageService
     * @return array
     */
    private static function getEligbleCampaigns($settings, $campaignMap, $context, $storageService)
    {
        $eligibleCampaigns = [];
        $eligibleCampaignsWithStorage = [];
        $inEligibleCampaigns = [];
        $campaignMapArray = $campaignMap;

        // Iterate over the campaign map to determine eligible campaigns
        foreach ($campaignMapArray as $featureKey => $campaigns) {
            foreach ($campaigns as $campaign) {
                $storedData = (new StorageDecorator())->getFeatureFromStorage($featureKey, $context, $storageService);

                // Check if campaign is stored in storage
                if (isset($storedData['experimentVariationId'])) {
                    if ($storedData['experimentKey'] && $storedData['experimentKey'] === $campaign->getKey()) {
                        $variation = CampaignUtil::getVariationFromCampaignKey(
                            $settings,
                            $storedData['experimentKey'],
                            $storedData['experimentVariationId']
                        );
                        if ($variation) {
                            LogManager::instance()->debug("MEG: Campaign {$storedData['experimentKey']} found in storage for user {$context['user']['id']}");

                            if (array_search($campaign->getKey(), array_column($eligibleCampaignsWithStorage, 'key')) === false) {
                                $eligibleCampaignsWithStorage[] = $campaign;
                            }
                            continue;
                        }
                    }
                }

                // Check if user is eligible for the campaign
                if (
                    (new CampaignDecisionService())->getPreSegmentationDecision(
                        (new CampaignModel())->modelFromDictionary($campaign),
                        $context
                    ) &&
                    (new CampaignDecisionService())->isUserPartOfCampaign($context->getId(), $campaign)
                ) {
                    $campaignKey = $campaign->getType() === CampaignTypeEnum::AB ? $campaign->getKey() : $campaign->getName() . '_' . $campaign->getRuleKey();
                    LogManager::instance()->info("Campaign {$campaignKey} is eligible for user ID:{$context->getId()}");

                    $eligibleCampaigns[] = $campaign;
                    continue;
                }

                $inEligibleCampaigns[] = $campaign;
            }
        }

        return [
            'eligibleCampaigns' => $eligibleCampaigns,
            'eligibleCampaignsWithStorage' => $eligibleCampaignsWithStorage,
            'inEligibleCampaigns' => $inEligibleCampaigns,
        ];
    }

    /**
     * Evaluates the eligible campaigns and determines the winner campaign based on the provided settings, feature key, eligible campaigns, eligible campaigns with storage, group ID, and context.
     *
     * @param SettingsModel $settings
     * @param string $featureKey
     * @param array $eligibleCampaigns
     * @param array $eligibleCampaignsWithStorage
     * @param int $groupId
     * @param ContextModel $context
     * @param StorageService $storageService
     * @return CampaignModel|null
     */
    private static function findWinnerCampaignAmongEligibleCampaigns($settings, $featureKey, $eligibleCampaigns, $eligibleCampaignsWithStorage, $groupId, $context, $storageService)
    {
        // getCampaignIds from featureKey
        $winnerCampaign = null;
        $campaignIds = CampaignUtil::getCampaignIdsFromFeatureKey($settings, $featureKey);
        // get the winner from each group and store it in winnerFromEachGroup
        $megAlgoNumber = (isset($settings->getGroups()->$groupId) && property_exists($settings->getGroups()->$groupId, 'et'))
            ? $settings->getGroups()->$groupId->et
            : Constants::RANDOM_ALGO;
        // if eligibleCampaignsWithStorage has only one campaign, then that campaign is the winner
        if (count($eligibleCampaignsWithStorage) === 1) {
            $winnerCampaign = $eligibleCampaignsWithStorage[0];
            LogManager::instance()->info(
                "MEG: Campaign " . 
                ($eligibleCampaignsWithStorage[0]->getType() === CampaignTypeEnum::AB
                    ? $eligibleCampaignsWithStorage[0]->getKey()
                    : $eligibleCampaignsWithStorage[0]->getName() . '_' . $eligibleCampaignsWithStorage[0]->getRuleKey()
                ) . 
                " is the winner for group " . $groupId . 
                " for user ID: " . $context->getId()
            );
        } elseif (count($eligibleCampaignsWithStorage) > 1 && $megAlgoNumber === Constants::RANDOM_ALGO) {
            // if eligibleCampaignsWithStorage has more than one campaign and algo is random, then find the winner using random algo
            $winnerCampaign = self::normalizeWeightsAndFindWinningCampaign(
                $eligibleCampaignsWithStorage,
                $context,
                $campaignIds,
                $groupId,
                $storageService
            );
        } elseif (count($eligibleCampaignsWithStorage) > 1) {
            // if eligibleCampaignsWithStorage has more than one campaign and algo is not random, then find the winner using advanced algo
            $winnerCampaign = self::getCampaignUsingAdvancedAlgo(
                $settings,
                $eligibleCampaignsWithStorage,
                $context,
                $campaignIds,
                $groupId,
                $storageService
            );
        }

        if (count($eligibleCampaignsWithStorage) === 0) {
            if (count($eligibleCampaigns) === 1) {
                $winnerCampaign = $eligibleCampaigns[0];
                $campaignKey = $eligibleCampaigns[0]->getType() === CampaignTypeEnum::AB ? $eligibleCampaigns[0]->getKey() : $eligibleCampaigns[0]->getName() . '_' . $eligibleCampaigns[0]->getRuleKey();
                LogManager::instance()->info("MEG: Campaign {$campaignKey} is the winner for group {$groupId} for user ID:{$context->getId()}");
            } elseif (count($eligibleCampaigns) > 1 && $megAlgoNumber === Constants::RANDOM_ALGO) {
                $winnerCampaign = self::normalizeWeightsAndFindWinningCampaign($eligibleCampaigns, $context, $campaignIds, $groupId, $storageService);
            } elseif (count($eligibleCampaigns) > 1) {
                $winnerCampaign = self::getCampaignUsingAdvancedAlgo($settings, $eligibleCampaigns, $context, $campaignIds, $groupId, $storageService);
            }
        }

        return $winnerCampaign;
    }

    /**
     * Normalizes the weights of shortlisted campaigns and determines the winning campaign using random allocation.
     *
     * @param array $shortlistedCampaigns
     * @param ContextModel $context
     * @param array $calledCampaignIds
     * @param int $groupId
     * @param StorageService $storageService
     * @return CampaignModel|null
     */
    private static function normalizeWeightsAndFindWinningCampaign($shortlistedCampaigns, $context, $calledCampaignIds, $groupId, $storageService)
    {
        // Normalize the weights of all the shortlisted campaigns
        foreach ($shortlistedCampaigns as $campaign) {
            $campaign->setWeight(round((100 / count($shortlistedCampaigns)) * 10000) / 10000);
        }

        // Convert campaigns to VariationModel objects (assuming a constructor exists)
        $shortlistedCampaigns = array_map(function ($campaign) {
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
            $data->ruleKey = $campaign->getRuleKey();

            return (new VariationModel())->modelFromDictionary($data); // Assuming constructor takes campaign object
        }, $shortlistedCampaigns);

        CampaignUtil::setCampaignAllocation($shortlistedCampaigns);
        $winnerCampaign = (new CampaignDecisionService())->getVariation(
            $shortlistedCampaigns,
            (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context->getId(), null, $groupId))
        );

        if ($winnerCampaign) {
            $campaignKey = $winnerCampaign->getType() === CampaignTypeEnum::AB ? $winnerCampaign->getKey() : $winnerCampaign->getKey() . '_' . $winnerCampaign->getRuleKey();
            LogManager::instance()->info("MEG: Campaign {$campaignKey} is the winner for group {$groupId} for user ID:{$context->getId()} using random algorithm");
            (new StorageDecorator())->setDataInStorage(
                [
                    'featureKey' => "_vwo_meta_meg_{$groupId}",
                    'context' => $context,
                    'experimentId' => $winnerCampaign->getId(),
                    'experimentKey' => $winnerCampaign->getKey(),
                    'experimentVariationId' => $winnerCampaign->getType() === CampaignTypeEnum::PERSONALIZE ? $winnerCampaign->getVariations()[0]->getId() : -1,
                ],
                $storageService
            );
            
            if (in_array($winnerCampaign->getId(), $calledCampaignIds)) {
                return $winnerCampaign;
            }
        }
        else {
            LogManager::instance()->info("No winner campaign found for MEG group: {$groupId}");
        }
        return null;
    }

    /**
     * Advanced algorithm to find the winning campaign based on priority order and weighted random distribution.
     *
     * @param SettingsModel $settings
     * @param array $shortlistedCampaigns
     * @param ContextModel $context
     * @param array $calledCampaignIds
     * @param int $groupId
     * @param StorageService $storageService
     * @return CampaignModel|null
     */
    private static function getCampaignUsingAdvancedAlgo($settings, $shortlistedCampaigns, $context, $calledCampaignIds, $groupId, $storageService)
    {
        $winnerCampaign = null;
        $found = false; // flag to check whether winnerCampaign has been found or not and helps to break from the outer loop
        $priorityOrder = isset($settings->getGroups()->$groupId->p) ? $settings->getGroups()->$groupId->p : [];
        $wt = isset($settings->getGroups()->$groupId->wt) ? $settings->getGroups()->$groupId->wt : [];

        for ($i = 0; $i < count($priorityOrder); $i++) {
            for ($j = 0; $j < count($shortlistedCampaigns); $j++) {
                if ((string)$shortlistedCampaigns[$j]->getId() === $priorityOrder[$i] || (string)$shortlistedCampaigns[$j]->getId() . '_' . $shortlistedCampaigns[$j]->getVariations()[0]->getId() === $priorityOrder[$i]) {
                    $winnerCampaign = FunctionUtil::cloneObject($shortlistedCampaigns[$j]);
                    $found = true;
                    break;
                }
            }
            if ($found === true) break;
        }

        // If winnerCampaign not found through Priority, then go for weighted Random distribution and for that,
        // Store the list of campaigns (participatingCampaigns) out of shortlistedCampaigns and their corresponding weights present in weightage distribution array (wt)
        if ($winnerCampaign === null) {
            $participatingCampaignList = [];
            // iterate over shortlisted campaigns and add weights from the weight array
            for ($i = 0; $i < count($shortlistedCampaigns); $i++) {
                $campaignId = $shortlistedCampaigns[$i]->getId();
                
                $clonedCampaign = FunctionUtil::cloneObject($shortlistedCampaigns[$i]);            

                if (isset($wt->$campaignId)) {
                    $clonedCampaign->setWeight($wt->$campaignId);
                    $participatingCampaignList[] = $clonedCampaign;
                } else if (isset($wt->{$campaignId . '_' . $shortlistedCampaigns[$i]->getVariations()[0]->getId()})) {
                    $clonedCampaign = FunctionUtil::cloneObject($shortlistedCampaigns[$i]);
                    $clonedCampaign->setWeight($wt->{$campaignId . '_' . $shortlistedCampaigns[$i]->getVariations()[0]->getId()});
                    $participatingCampaignList[] = $clonedCampaign;
                }
            }
            /* Finding winner campaign using weighted Distibution :
              1. Re-distribute the traffic by assigning range values for each camapign in particaptingCampaignList
              2. Calculate bucket value for the given userId and groupId
              3. Get the winnerCampaign by checking the Start and End Bucket Allocations of each campaign
            */
            
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
                $data->ruleKey = $campaign->getRuleKey();

                return (new VariationModel())->modelFromDictionary($data); // Assuming constructor takes campaign object
            }, $participatingCampaignList);

            CampaignUtil::setCampaignAllocation($participatingCampaignList);
            $winnerCampaign = (new CampaignDecisionService())->getVariation(
                $participatingCampaignList,
                (new DecisionMaker())->calculateBucketValue(CampaignUtil::getBucketingSeed($context->getId(), null, $groupId))
            );
        }        
        
        // WinnerCampaign should not be null, in case when winnerCampaign hasn't been found through PriorityOrder and
        // also shortlistedCampaigns and wt array does not have a single campaign id in common

        if ($winnerCampaign) {
            $campaignKey = $winnerCampaign->getType() === CampaignTypeEnum::AB ? $winnerCampaign->getKey() : $winnerCampaign->getKey() . '_' . $winnerCampaign->getRuleKey();
            LogManager::instance()->info("MEG: Campaign {$campaignKey} is the winner for group {$groupId} for user ID:{$context->getId()} using advanced algorithm");
        } else {
            LogManager::instance()->info("No winner campaign found for MEG group: {$groupId}");
        }

        if ($winnerCampaign) {
            (new StorageDecorator())->setDataInStorage(
                [
                    'featureKey' => "_vwo_meta_meg_{$groupId}",
                    'context' => $context,
                    'experimentId' => $winnerCampaign->getId(),
                    'experimentKey' => $winnerCampaign->getKey(),
                    'experimentVariationId' => $winnerCampaign->getType() === CampaignTypeEnum::PERSONALIZE ? $winnerCampaign->getVariations()[0]->getId() : -1,
                ],
                $storageService
            );
            if (in_array($winnerCampaign->getId(), $calledCampaignIds)) {
                return $winnerCampaign;
            }
        }
        return null;
    }
}
?>
