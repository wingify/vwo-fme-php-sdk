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

        if ($feature && !$feature->getRulesLinkedCampaign()) {
            return [];
        }

        if ($feature && $feature->getRulesLinkedCampaign() && $type && is_string($type)) {
            $arraylist = array_filter($feature->getRulesLinkedCampaign(), function ($rule) use ($type) {
                return is_array($rule) && array_key_exists('type', $rule) && $rule["type"] === $type;
            });
            return $arraylist;
        }
        return $feature->getRulesLinkedCampaign();
    }

    public static function getAllAbAndPersonaliseRules($settings, $featureKey)
    {
        $feature = self::getFeatureFromKey($settings, $featureKey);
        // Get the rules linked to the campaign and ensure they are RuleModel objects
        $rulesLinkedCampaign = $feature->getRulesLinkedCampaign();

        // Filter and return the rules that are of type 'AB' or 'Personalize'
        return array_filter($rulesLinkedCampaign, function ($rule) {
            $ruleModel = new RuleModel();
            $ruleModel->modelFromDictionary($rule);
            // Check if the rule is an instance of RuleModel and check the 'type' property
            return $rule &&
                ($rule['type'] === CampaignTypeEnum::AB || $rule['type'] === CampaignTypeEnum::PERSONALIZE);
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
                    $campaigns = array_values(array_filter($settingsFile->getCampaigns(), function($c) use ($campaignId) {
                        return $c->getId() === $campaignId;
                    }));
                    if (!empty($campaigns)) {
                        $campaign = $campaigns[0];
                        $linkedCampaign = (array) $campaign;
                        $linkedCampaign = array_merge((array) $rule, $linkedCampaign);
                        $variationId = $rule->getVariationId();
                        if ($variationId) {
                            $variations = array_values(array_filter($campaign->getVariations(), function($v) use ($variationId) {
                                return $v->getId() === $variationId;
                            }));
                            if (!empty($variations)) {
                                $variation = $variations[0];
                                $linkedCampaign['variations'] = [$variation];
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
        $features = array_values(array_filter($settings->getFeatures(), function($f) use ($featureKey) {
            return $f->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0]->getName() : '';
    }

    public static function getFeatureIdFromKey(SettingsModel $settings, $featureKey)
    {
        $features = array_values(array_filter($settings->getFeatures(), function($f) use ($featureKey) {
            return $f->getKey() === $featureKey;
        }));
        return !empty($features) ? $features[0]->getId() : null;
    }

    public static function convertObjectToArray($object) {
        $v1 = json_encode($object);
        $v2 = json_decode($v1, true);
        return $v2;
        //return json_decode(json_encode($object), true);
    }

    public static function convertObjectToArray1($object) {
        if (is_object($object)) {
            $reflectionClass = new \ReflectionClass(get_class($object));
            $array = [];
            foreach ($reflectionClass->getProperties() as $property) {
                $property->setAccessible(true);
                $array[$property->getName()] = self::convertObjectToArray1($property->getValue($object));
            }
            return $array;
        }

        if (is_array($object)) {
            foreach ($object as $key => $value) {
                $object[$key] = self::convertObjectToArray1($value);
            }
        }
        return $object;
    }

}
