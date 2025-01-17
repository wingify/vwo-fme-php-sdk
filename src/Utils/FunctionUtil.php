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

use vwo\Enums\CampaignTypeEnum;
use vwo\Models\SettingsModel;
use vwo\Models\FeatureModel;
use vwo\Models\CampaignModel;

class FunctionUtil
{
    /**
     * Clones an object deeply.
     * @param mixed $obj - The object to clone.
     * @return mixed The cloned object.
     */
    public static function cloneObject($obj)
    {
        if (!$obj) {
            return $obj;
        }
        return clone $obj;
    }

    /**
     * Gets the current Unix timestamp in seconds.
     * @return int The current Unix timestamp.
     */
    public static function getCurrentUnixTimestamp()
    {
        return (int) ceil(microtime(true));
    }

    /**
     * Gets the current Unix timestamp in milliseconds.
     * @return int The current Unix timestamp in milliseconds.
     */
    public static function getCurrentUnixTimestampInMillis()
    {
        return (int) (microtime(true) * 1000);
    }

    /**
     * Generates a random number between 0 and 1.
     * @return float A random number.
     */
    public static function getRandomNumber()
    {
        return mt_rand() / mt_getrandmax();
    }

    /**
     * Retrieves specific rules based on the type from a feature.
     * @param FeatureModel $feature - The feature object.
     * @param string|null $type - The type of the rules to retrieve.
     * @return array An array of rules that match the type.
     */
    public static function getSpecificRulesBasedOnType($feature, $type = null)
    {
        if ($feature && !$feature->getRulesLinkedCampaign()) {
            return [];
        }

        if ($feature && $feature->getRulesLinkedCampaign() && $type && is_string($type)) {
            return array_filter($feature->getRulesLinkedCampaign(), function ($rule) use ($type) {
                $ruleModel = (new CampaignModel())->modelFromDictionary($rule);
                return $ruleModel->getType() === $type;
            });
        }

        return $feature->getRulesLinkedCampaign();
    }

    /**
     * Retrieves all AB and Personalize rules from a feature.
     * @param FeatureModel $feature - The feature containing rules.
     * @return array An array of AB and Personalize rules.
     */
    public static function getAllExperimentRules($feature)
    {
        // Retrieve the rules linked to the campaign
        $rulesLinkedCampaign = $feature->getRulesLinkedCampaign();

        // Filter and return the rules that are of type 'AB' or 'Personalize'
        return array_filter($rulesLinkedCampaign, function ($rule) {
            return $rule &&
                ($rule->getType() === CampaignTypeEnum::AB || $rule->getType() === CampaignTypeEnum::PERSONALIZE);
        });
    }

    /**
     * Retrieves a feature by its key from the settings.
     * @param SettingsModel $settings - The settings containing features.
     * @param string $featureKey - The key of the feature to find.
     * @return mixed The feature if found, otherwise null.
     */
    public static function getFeatureFromKey($settings, $featureKey)
    {
        foreach ($settings->getFeatures() as $feature) {
            if ($feature->getKey() === $featureKey) {
                return $feature;
            }
        }
        return null;
    }

    /**
     * Checks if an event exists within any feature's metrics.
     * @param string $eventName - The name of the event to check.
     * @param SettingsModel $settings - The settings containing features.
     * @return bool True if the event exists, otherwise false.
     */
    public static function doesEventBelongToAnyFeature($eventName, $settings)
    {
        foreach ($settings->getFeatures() as $feature) {
            foreach ($feature->getMetrics() as $metric) {
                if ($metric->getIdentifier() === $eventName) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Adds linked campaigns to each feature in the settings based on rules.
     * @param SettingsModel $settings - The settings file to modify.
     */
    public static function addLinkedCampaignsToSettings($settings)
    {
        // Create a map for quick access to campaigns
        $campaignMap = [];
        foreach ($settings->getCampaigns() as $campaign) {
            $campaignMap[$campaign->getId()] = $campaign;
        }

        // Loop over all features
        foreach ($settings->getFeatures() as $feature) {
            $rulesLinkedCampaign = [];
            foreach ($feature->getRules() as $rule) {
                $campaignId = $rule->getCampaignId();
                if (isset($campaignMap[$campaignId])) {
                    $campaign = clone $campaignMap[$campaignId];
                    $campaign->setStatus($rule->getStatus());
                    $campaign->setVariationId($rule->getVariationId());
                    $campaign->setType($rule->getType());
                    $campaign->setCampaignId($rule->getCampaignId());
                    $campaign->setRuleKey($rule->getRuleKey());

                    if ($variationId = $rule->getVariationId()) {
                        $variations = array_filter($campaign->getVariations(), function ($v) use ($variationId) {
                            return $v->getId() === $variationId;
                        });
                        if (!empty($variations)) {
                            $campaign->setVariations([reset($variations)]);
                        }
                    }

                    $rulesLinkedCampaign[] = $campaign;
                }
            }
            $feature->setRulesLinkedCampaign($rulesLinkedCampaign);
        }
    }

    /**
     * Retrieves the feature name by its key.
     * @param SettingsModel $settings - The settings containing features.
     * @param string $featureKey - The key of the feature.
     * @return string The feature name if found, otherwise an empty string.
     */
    public static function getFeatureNameFromKey(SettingsModel $settings, $featureKey)
    {
        $features = array_values(array_filter($settings->getFeatures(), function ($f) use ($featureKey) {
            return $f->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0]->getName() : '';
    }

    /**
     * Retrieves the feature ID by its key.
     * @param SettingsModel $settings - The settings containing features.
     * @param string $featureKey - The key of the feature.
     * @return int|null The feature ID if found, otherwise null.
     */
    public static function getFeatureIdFromKey(SettingsModel $settings, $featureKey)
    {
        $features = array_values(array_filter($settings->getFeatures(), function ($f) use ($featureKey) {
            return $f->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0]->getId() : null;
    }
}
