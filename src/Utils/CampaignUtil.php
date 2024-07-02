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

use vwo\Constants\Constants;
use vwo\Models\CampaignModel;
use vwo\Models\VariationModel;
use vwo\Enums\CampaignTypeEnum;
use vwo\Models\SettingsModel;
use vwo\Packages\Logger\Core\LogManager;

class CampaignUtil
{

    public static function setVariationAllocation($campaign)
    {
        if ($campaign->getType() === CampaignTypeEnum::ROLLOUT || $campaign->getType() === CampaignTypeEnum::PERSONALIZE) {
            // Handle special logic for rollout or personalize campaigns
            self::handleRolloutCampaign($campaign);
        } else {
            $stepFactor = 0;
            $numberOfVariations = count($campaign->getVariations());
            for ($i = 0, $currentAllocation = 0; $i < $numberOfVariations; $i++) {
                $variation = $campaign->getVariations()[$i];

                $stepFactor = self::assignRangeValues($variation, $currentAllocation);
                $currentAllocation += $stepFactor;
                LogManager::instance()->debug(
                    "VARIATION_RANGE_ALLOCATION: Variation:{$variation->getKey()} of Campaign:{$campaign->getKey()} having weight:{$variation->getWeight()} got bucketing range: ( {$variation->getStartRangeVariation()} - {$variation->getEndRangeVariation()} )"
                );
            }
        }
    }

    private static function handleRolloutCampaign(CampaignModel $campaign)
    {
        // Set start and end ranges for all variations in the campaign
        $variations = $campaign->getVariations();
        foreach ($variations as $variation) {
            $endRange = $variation->getWeight() * 100;
            $variation->setStartRange(1);
            $variation->setEndRange($endRange);
            LogManager::instance()->debug(
                "VARIATION_RANGE_ALLOCATION: Variation:{$variation->getKey()} of Campaign:{$campaign->getKey()} got bucketing range: ( 1 - {$endRange} )"
            );
        }
    }


    private static function copyVariableData(array $variationVariable, array $featureVariable)
    {
        // Create a featureVariableMap
        $featureVariableMap = [];
        foreach ($featureVariable as $variable) {
            $featureVariableMap[$variable->getId()] = $variable;
        }
        foreach ($variationVariable as $variable) {
            $featureVariable = $featureVariableMap[$variable->getId()] ?? null;
            if ($featureVariable) {
                $variable->setKey($featureVariable->getKey());
                $variable->setType($featureVariable->getType());
            }
        }
    }

    public static function assignRangeValues(VariationModel $data, int $currentAllocation)
    {
        $dataWeight = $data->getWeight();
        $stepFactor = self::getVariationBucketRange($dataWeight);

        if ($stepFactor) {
            $data->setStartRange($currentAllocation + 1);
            $data->setEndRange($currentAllocation + $stepFactor);
        } else {
            $data->setStartRange(-1);
            $data->setEndRange(-1);
        }
        return $stepFactor;
    }

    private static function getVariationBucketRange(&$variationWeight)
    {
        if (!$variationWeight || $variationWeight === 0) {
            return 0;
        }

        $startRange = ceil($variationWeight * 100);

        return min($startRange, Constants::MAX_TRAFFIC_VALUE);
    }

    public static function scaleVariationWeights(&$variations)
    {
        $totalWeight = array_reduce($variations, function ($acc, $variation) {
            return $acc + $variation['weight'];
        }, 0);

        if (!$totalWeight) {
            $weight = 100 / count($variations);
            foreach ($variations as &$variation) {
                $variation['weight'] = $weight;
            }
        } else {
            foreach ($variations as &$variation) {
                $variation['weight'] = ($variation['weight'] / $totalWeight) * 100;
            }
        }
    }

    public static function getBucketingSeed($userId, $campaign, $groupId = null)
    {
        if ($groupId) {
            return "{$groupId}_{$userId}";
        }
        return "{$campaign->id}_{$userId}";
    }

    public static function getCampaignVariation($settings, $campaignKey, $variationId)
    {
        $campaign = null;
        foreach ($settings->getCampaigns() as $campaignItem) {
            if ($campaignItem->getKey() === $campaignKey) {
                $campaign = $campaignItem;
                break;
            }
        }

        if ($campaign) {
            $variation = null;
            foreach ($campaign->getVariations() as $variationItem) {
                if ($variationItem->getId() === $variationId) {
                    $variation = $variationItem;
                    break;
                }
            }

            if ($variation) {
                return (new VariationModel())->modelFromDictionary($variation);
            }
        }
        return null;
    }

    public static function getRolloutVariation($settings, string $rolloutKey, $variationId)
    {
        $rolloutCampaign = null;
        foreach ($settings->getCampaigns() as $campaign) {
            if ($campaign->getKey() === $rolloutKey) {
                $rolloutCampaign = $campaign;
                break;
            }
        }

        if ($rolloutCampaign) {
            $variation = null;
            foreach ($rolloutCampaign->getVariations() as $var) {
                if ($var->getId() === $variationId) {
                    $variation = $var;
                    break;
                }
            }

            if ($variation) {
                return (new VariationModel())->modelFromDictionary($variation);
            }
        }
        return null;
    }

    public static function setCampaignAllocation(array &$campaigns)
    {
        $stepFactor = 0;
        $currentAllocation = 0;

        foreach ($campaigns as &$campaign) {
            $stepFactor = self::assignRangeValuesMEG($campaign, $currentAllocation);
            $currentAllocation += $stepFactor;
        }
    }

    public static function isPartOfGroup($settings, $campaignId)
    {
        // Check if campaignGroups property exists and the campaignId exists within it
        if (!empty($settings->getCampaignGroups()) && !empty($settings->getCampaignGroups()->{$campaignId})) {
            $groupId = $settings->getCampaignGroups()->{$campaignId};

            // Ensure groupId is used correctly to access settings->groups
            if (isset($settings->getGroups()->{$groupId})) {
                $group = $settings->getGroups()->{$groupId};

                // Ensure $group is an object and contains the 'name' property
                if (is_object($group) && isset($group->name)) {
                    return [
                        'groupId' => $groupId,
                        'groupName' => $group->name
                    ];
                }
            }
        }
        return [];
    }

    public static function findGroupsFeaturePartOf($settings, string $featureKey)
    {
        $campaignIds = [];
        foreach ($settings->features as $feature) {
            if ($feature->key === $featureKey) {
                foreach ($feature->rules as $rule) {
                    if (!in_array($rule->campaignId, $campaignIds)) {
                        $campaignIds[] = $rule->campaignId;
                    }
                }
            }
        }

        $groups = [];
        foreach ($campaignIds as $campaignId) {
            $group = self::isPartOfGroup($settings, $campaignId);
            if (!empty($group['groupId'])) {
                $groupIndex = array_search($group['groupId'], array_column($groups, 'groupId'));
                if ($groupIndex === false) {
                    $groups[] = $group;
                }
            }
        }
        return $groups;
    }

    public static function getCampaignsByGroupId($settings, $groupId)
    {
        $groups = $settings->getGroups();

        // Check if the group exists in the object
        if (isset($groups->{$groupId})) {
            return $groups->{$groupId}->campaigns;
        } else {
            return []; // Return an empty array if the group ID is not found
        }
    }

    public static function getFeatureKeysFromCampaignIds($settings, $campaignIds)
    {
        $featureKeys = [];
        foreach ($campaignIds as $campaignId) {
            foreach ($settings->getFeatures() as $feature) {
                foreach ($feature->getRules() as $rule) {
                    if ($rule->getCampaignId() === $campaignId) {
                        $featureKeys[] = $feature->getKey();
                    }
                }
            }
        }
        return $featureKeys;
    }

    public static function getCampaignIdsFromFeatureKey(SettingsModel $settings, string $featureKey)
    {
        $campaignIds = [];
        foreach ($settings->getFeatures() as $feature) {
            if ($feature->getKey() === $featureKey) {
                foreach ($feature->getRules() as $rule) {
                    $campaignIds[] = $rule->getCampaignId();
                }
            }
        }
        return $campaignIds;
    }
    private static function assignRangeValuesMEG(object &$data, int $currentAllocation)
    {
        $weight = $data->getWeight(); // Store the result of getWeight() in a variable
        $stepFactor = self::getVariationBucketRange($weight); // Pass the variable to the method

        if ($stepFactor) {
            $data->setStartRange($currentAllocation + 1);
            $data->setEndRange($currentAllocation + $stepFactor);
        } else {
            $data->setStartRange(-1);
            $data->setEndRange(-1);
        }
        return $stepFactor;
    }

    public static function getRuleTypeUsingCampaignIdFromFeature($feature, $campaignId)
    {
        $ruleType = '';

        foreach ($feature->getRules() as $rule) {
            if ($rule->getCampaignId() === $campaignId) {
                $ruleType = $rule->getType();
                break;
            }
        }
        return $ruleType;
    }
}
