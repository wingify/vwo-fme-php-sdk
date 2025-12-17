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
use vwo\Enums\CampaignTypeEnum;
use vwo\Models\CampaignModel;
use vwo\Models\FeatureModel;
use vwo\Models\VariationModel;
use vwo\Models\SettingsModel;

class CampaignUtil
{
    /**
     * Sets the variation allocation for a given campaign based on its type.
     * If the campaign type is ROLLOUT or PERSONALIZE, it handles the campaign using `handleRolloutCampaign`.
     * Otherwise, it assigns range values to each variation in the campaign.
     * @param CampaignModel $campaign The campaign for which to set the variation allocation.
     */
    public static function setVariationAllocation($campaign, $logManager)
    {
        if ($campaign->getType() === CampaignTypeEnum::ROLLOUT || $campaign->getType() === CampaignTypeEnum::PERSONALIZE) {
            self::handleRolloutCampaign($campaign, $logManager);
        } else {
            $currentAllocation = 0;
            foreach ($campaign->getVariations() as $variation) {
                $stepFactor = self::assignRangeValues($variation, $currentAllocation);
                $currentAllocation += $stepFactor;

                $logManager->info(
                    sprintf(
                        "VARIATION_RANGE_ALLOCATION: Variation:%s of Campaign:%s having weight:%s got bucketing range: ( %s - %s )",
                        $variation->getKey(),
                        $campaign->getKey(),
                        $variation->getWeight(),
                        $variation->getStartRangeVariation(),
                        $variation->getEndRangeVariation()
                    )
                );
            }
        }
    }

    /**
     * Assigns start and end range values to a variation based on its weight.
     * @param VariationModel $data The variation model to assign range values.
     * @param int $currentAllocation The current allocation value before this variation.
     * @return int The step factor calculated from the variation's weight.
     */
    public static function assignRangeValues($data, $currentAllocation)
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

    /**
     * Scales the weights of variations to sum up to 100%.
     * @param array $variations The list of variations to scale.
     */
    public static function scaleVariationWeights($variations)
    {
        $totalWeight = array_reduce($variations, function ($acc, $variation) {
            return $acc + $variation->getWeight();
        }, 0);

        if (!$totalWeight) {
            $equalWeight = 100 / count($variations);
            foreach ($variations as $variation) {
                $variation->setWeight($equalWeight);
            }
        } else {
            foreach ($variations as $variation) {
                $variation->setWeight(($variation->getWeight() / $totalWeight) * 100);
            }
        }
    }

    /**
     * Generates a bucketing seed based on user ID, campaign, and optional group ID.
     * @param string $userId The user ID.
     * @param CampaignModel $campaign The campaign object.
     * @param int|null $groupId The optional group ID.
     * @return string The bucketing seed.
     */
    public static function getBucketingSeed($userId, $campaign, $groupId = null)
    {
        if ($groupId) {
            return "{$groupId}_{$userId}";
        }
        // Determine if the campaign is of type ROLLOUT or PERSONALIZE
        $isRolloutOrPersonalize = $campaign->getType() === CampaignTypeEnum::ROLLOUT || $campaign->getType() === CampaignTypeEnum::PERSONALIZE;
    
        // Get the salt based on the campaign type
        $salt = $isRolloutOrPersonalize ? $campaign->getVariations()[0]->getSalt() : $campaign->getSalt();
    
        if (!empty($salt)) {
            return "{$salt}_{$userId}";
        } else {
            return "{$campaign->getId()}_{$userId}";
        }
    }

    /**
     * Retrieves a variation by its ID within a specific campaign identified by its key.
     * @param SettingsModel $settings The settings model containing all campaigns.
     * @param string $campaignKey The key of the campaign.
     * @param int $variationId The ID of the variation to retrieve.
     * @return VariationModel|null The found variation model or null if not found.
     */
    public static function getVariationFromCampaignKey($settings, $campaignKey, $variationId)
    {
        $campaign = array_values(array_filter($settings->getCampaigns(), function ($campaign) use ($campaignKey) {
            return $campaign->getKey() === $campaignKey;
        }))[0] ?? null;

        if ($campaign) {
            $variation = array_values(array_filter($campaign->getVariations(), function ($variation) use ($variationId) {
                return $variation->getId() === $variationId;
            }))[0] ?? null;

            if ($variation) {
                return (new VariationModel())->modelFromDictionary($variation);
            }
        }
        return null;
    }

    /**
     * Sets the allocation ranges for a list of campaigns.
     * @param array $campaigns The list of campaigns to set allocations for.
     */
    public static function setCampaignAllocation($campaigns)
    {
        $stepFactor = 0;
        for ($i = 0, $currentAllocation = 0; $i < count($campaigns); $i++) {
            $campaign = $campaigns[$i];
            $stepFactor = self::assignRangeValuesMEG($campaign, $currentAllocation);
            $currentAllocation += $stepFactor;
        }
    }

    /**
     * Determines if a campaign is part of a group.
     * @param SettingsModel $settings The settings model containing group associations.
     * @param int $campaignId The ID of the campaign to check.
     * @param int|null $variationId The optional variation ID.
     * @return array An object containing the group ID and name if the campaign is part of a group, otherwise an empty object.
     */
    public static function getGroupDetailsIfCampaignPartOfIt($settings, $campaignId, $variationId = null)
    {
        // Force campaignId to be a string to ensure proper comparison
        $campaignId = (string) $campaignId;

        // Determine the campaign key to check (with variation if provided)
        $campaignToCheck = $campaignId;

        if ($variationId !== null) {
            $campaignToCheck .= '_' . $variationId;
        }

        // Trim any whitespace to avoid hidden characters causing mismatches
        $campaignToCheck = trim($campaignToCheck);
        $campaignToCheck = (string)($campaignToCheck);


        // Get campaign groups as an array
        $campaignGroups = $settings->getCampaignGroups();
        $campaignGroups = json_decode(json_encode($campaignGroups), true);

        // Check if the campaign exists in the groups mapping using array_key_exists
        if (!empty($campaignGroups) && isset($campaignGroups[$campaignToCheck])) {

            // If the campaign exists, get the corresponding group ID
            $groupId = $campaignGroups[$campaignToCheck];  // This gets the correct value from the original array
            // Get group details as an array
            $groupDetails = json_decode(json_encode($settings->getGroups()), true);
            $groupId = (string) $groupId;

            // Check if the group ID exists in the group details
            if (isset($groupDetails[$groupId])) {
                $group = $groupDetails[$groupId];
                $groupName = '';

                // Support both object and array access for group name
                if (is_object($group) && isset($group->name)) {
                    $groupName = $group->name;
                } elseif (is_array($group) && isset($group['name'])) {
                    $groupName = $group['name'];
                }

                return array(
                    'groupId' => $groupId,
                    'groupName' => $groupName
                );
            }
        }
        return [];
    }

    /**
     * Finds all groups associated with a feature specified by its key.
     * @param SettingsModel $settings The settings model containing all features and groups.
     * @param string $featureKey The key of the feature to find groups for.
     * @return array An array of groups associated with the feature.
     */
    public static function findGroupsFeaturePartOf($settings, $featureKey)
    {
        $ruleArray = [];

        foreach ($settings->getFeatures() as $feature) {
            if ($feature->getKey() === $featureKey) {
                foreach ($feature->getRules() as $rule) {
                    if (!in_array($rule, $ruleArray)) {
                        $ruleArray[] = $rule;
                    }
                }
            }
        }

        $groups = [];
        foreach ($ruleArray as $rule) {
            $group = self::getGroupDetailsIfCampaignPartOfIt(
                $settings,
                $rule->getCampaignId(),
                $rule->getType() === CampaignTypeEnum::PERSONALIZE ? $rule->getVariationId() : null
            );
            if (!empty($group)) {
                $groupIndex = array_search($group['groupId'], array_column($groups, 'groupId'));
                if ($groupIndex === false) {
                    $groups[] = $group;
                }
            }
        }
        return $groups;
    }

    /**
     * Retrieves campaigns by a specific group ID.
     * @param SettingsModel $settings The settings model containing all groups.
     * @param int $groupId The ID of the group.
     * @return array An array of campaigns associated with the specified group ID.
     */
    public static function getCampaignsByGroupId($settings, $groupId)
    {
        $campaignGroups = json_decode(json_encode($settings->getGroups()), true);
        $group = $campaignGroups[$groupId] ?? null;
        if ($group) {
            return $group["campaigns"];
        } else {
            return [];
        }
    }

    /**
     * Retrieves feature keys from a list of campaign IDs.
     * @param SettingsModel $settings The settings model containing all features.
     * @param array $campaignIdWithVariation An array of campaign IDs and variation IDs in the format campaignId_variationId.
     * @return array An array of feature keys associated with the provided campaign IDs.
     */
    public static function getFeatureKeysFromCampaignIds($settings, $campaignIdWithVariation)
    {
        $featureKeys = [];

        foreach ($campaignIdWithVariation as $campaign) {
            // Split the key with '_' to separate campaignId and variationId
            list($campaignId, $variationId) = array_pad(explode('_', $campaign), 2, null);

            foreach ($settings->getFeatures() as $feature) {
                if(in_array($feature->getKey(), $featureKeys)){
                    continue;
                }
                foreach ($feature->getRules() as $rule) {
                    if ($rule->getCampaignId() == $campaignId) {
                        // Check if variationId is provided and matches the rule's variationId
                        if ($variationId !== null) {
                            // Add feature key if variationId matches
                            if ($rule->getVariationId() == $variationId) {
                                $featureKeys[] = $feature->getKey();
                            }
                        } else {
                            // Add feature key if no variationId is provided
                            $featureKeys[] = $feature->getKey();
                        }
                    }
                }
            }
        }
        return $featureKeys;
    }

    /**
     * Retrieves campaign IDs from a specific feature key.
     * @param SettingsModel $settings The settings model containing all features.
     * @param string $featureKey The key of the feature.
     * @return array An array of campaign IDs associated with the specified feature key.
     */
    public static function getCampaignIdsFromFeatureKey($settings, $featureKey)
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

    /**
     * Assigns range values to a campaign based on its weight.
     * @param mixed $data The campaign data containing weight.
     * @param int $currentAllocation The current allocation value before this campaign.
     * @return int The step factor calculated from the campaign's weight.
     */
    public static function assignRangeValuesMEG($data, $currentAllocation)
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

    /**
     * Retrieves the rule type using a campaign ID from a specific feature.
     * @param FeatureModel $feature The feature containing rules.
     * @param int $campaignId The campaign ID to find the rule type for.
     * @return string The rule type if found, otherwise an empty string.
     */
    public static function getRuleTypeUsingCampaignIdFromFeature($feature, $campaignId)
    {
        $rule = array_values(array_filter($feature->getRules(), function ($rule) use ($campaignId) {
            return $rule->getCampaignId() === $campaignId;
        }))[0] ?? null;

        return $rule ? $rule->getType() : '';
    }

    /**
     * Calculates the bucket range for a variation based on its weight.
     * @param int $variationWeight The weight of the variation.
     * @return int The calculated bucket range.
     */
    private static function getVariationBucketRange($variationWeight)
    {
        if (!$variationWeight || $variationWeight === 0) {
            return 0;
        }

        $startRange = ceil($variationWeight * 100);

        return min($startRange, Constants::MAX_TRAFFIC_VALUE);
    }

    /**
     * Handles the rollout campaign by setting start and end ranges for all variations.
     * @param CampaignModel $campaign The campaign to handle.
     */
    private static function handleRolloutCampaign($campaign, $logManager)
    {
        foreach ($campaign->getVariations() as $variation) {
            $endRange = $variation->getWeight() * 100;

            $variation->setStartRange(1);
            $variation->setEndRange($endRange);

            $logManager->info(
                sprintf(
                    "VARIATION_RANGE_ALLOCATION: Variation:%s of Campaign:%s got bucketing range: ( 1 - %s )",
                    $variation->getKey(),
                    $campaign->getKey(),
                    $endRange
                )
            );
        }
    }
}
