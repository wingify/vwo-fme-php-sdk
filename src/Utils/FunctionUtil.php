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
use vwo\Enums\CampaignTypeEnum;
use vwo\Models\RuleModel;

class FunctionUtil
{
    public static function cloneObject($obj)
    {
        if (!$obj) {
            return $obj;
        }
        return json_decode(json_encode($obj), true);
    }

    public static function getCurrentUnixTimestamp()
    {
        return (int)ceil(microtime(true));
    }

    public static function getCurrentUnixTimestampInMillis()
    {
        return (int)(microtime(true) * 1000);
    }

    public static function getRandomNumber()
    {
        return mt_rand() / mt_getrandmax();
    }


    public static function getSpecificRulesBasedOnType($settings, $featureKey, $type = null)
    {
        $feature = self::getFeatureFromKey($settings, $featureKey);

        // Check if the feature exists and has linked rules
        if (!$feature || !$feature->getRulesLinkedCampaign()) {
            return [];
        }

        // If type is specified and it's a string, filter the rules based on the type
        if ($type && is_string($type)) {
            $filteredRules = array_filter($feature->getRulesLinkedCampaign(), function ($rule) use ($type) {
                return $rule && $rule->getType() === $type;
            });
            return $filteredRules;
        }

        // Return all linked rules if no type is specified
        return $feature->getRulesLinkedCampaign();
    }

    public static function getAllAbAndPersonaliseRules($settings, $featureKey)
    {
        $feature = self::getFeatureFromKey($settings, $featureKey);
        // Get the rules linked to the campaign and ensure they are RuleModel objects
        $rulesLinkedCampaign = $feature->getRulesLinkedCampaign();

        // Filter and return the rules that are of type 'AB' or 'Personalize'
        return array_filter($rulesLinkedCampaign, function ($rule) {
            // Check if the rule is an instance of RuleModel and check the 'type' property
            return $rule &&
                ($rule->getType() === CampaignTypeEnum::AB || $rule->getType() === CampaignTypeEnum::PERSONALIZE);
        });
    }

    public static function getFeatureFromKey($settings, $featureKey)
    {
        $features = array_values(array_filter($settings->getFeatures(), function ($feature) use ($featureKey) {
            return $feature->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0] : null;
    }

    public static function eventExists($eventName, $settings)
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

    public static function addLinkedCampaignsToSettings($settingsFile)
    {
        foreach ($settingsFile->getFeatures() as $feature) {
            $rulesLinkedCampaign = [];
            foreach ($feature->getRules() as $rule) {
                $campaignId = $rule->getCampaignId();
                if ($campaignId) {

                    $campaigns = array_values(array_filter($settingsFile->getCampaigns(), function ($c) use ($campaignId) {
                        return $c->getId() === $campaignId;
                    }));
                    if (!empty($campaigns)) {
                        $campaign = $campaigns[0];
                        $linkedCampaign = clone $campaign;

                        // Merge $rule properties into $linkedCampaign using getter methods
                        $linkedCampaign->setStatus($rule->getStatus());
                        $linkedCampaign->setVariationId($rule->getVariationId());
                        $linkedCampaign->setType($rule->getType());
                        $linkedCampaign->setCampaignId($rule->getCampaignId());
                        $linkedCampaign->setRuleKey($rule->getRuleKey());

                        $variationId = $rule->getVariationId();
                        if ($variationId) {
                            $variations = array_values(array_filter($campaign->getVariations(), function ($v) use ($variationId) {
                                return $v->getId() === $variationId;
                            }));
                            if (!empty($variations)) {
                                $variation = $variations[0];
                                $linkedCampaign->setVariations([$variation]);
                            }
                        }
                        $rulesLinkedCampaign[] = $linkedCampaign;
                    }
                }
            }
            $feature->setRulesLinkedCampaign($rulesLinkedCampaign);
        }
    }

    public static function getFeatureNameFromKey(SettingsModel $settings, $featureKey)
    {
        $features = array_values(array_filter($settings->getFeatures(), function ($f) use ($featureKey) {
            return $f->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0]->getName() : '';
    }

    public static function getFeatureIdFromKey(SettingsModel $settings, $featureKey)
    {
        $features = array_values(array_filter($settings->getFeatures(), function ($f) use ($featureKey) {
            return $f->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0]->getId() : null;
    }
}
