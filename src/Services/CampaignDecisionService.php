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

namespace vwo\Services;

use vwo\Packages\DecisionMaker\DecisionMaker as DecisionMaker;
use vwo\Packages\Logger\Core\LogManager;
use vwo\Models\VariableModel;
use vwo\Enums\CampaignTypeEnum;
use vwo\Constants\Constants;
use vwo\Utils\DataTypeUtil;
use vwo\Packages\SegmentationEvaluator\Core\SegmentationManager;
use vwo\Utils\FunctionUtil;

interface ICampaignDecisionService {
    public function isUserPartOfCampaign($userId, $campaign);
    public function getVariation($variations, $bucketValue);
    public function checkInRange($variation, $bucketValue);
    public function bucketUserToVariation($userId, $accountId, $campaign);
    public function getDecision($campaign, $settings, $context);
    public function getVariationAlloted($userId, $accountId, $campaign);
}

class CampaignDecisionService implements ICampaignDecisionService {
    public function isUserPartOfCampaign($userId, $campaign) {
        if (!$campaign || !$userId) {
            return false;
        }
        
        $trafficAllocation = $campaign->getType() === CampaignTypeEnum::ROLLOUT || $campaign->getType() === CampaignTypeEnum::PERSONALIZE
            ? $campaign->getVariations()[0]->getWeight()
            : $campaign->getTraffic();
        
        $valueAssignedToUser = (new DecisionMaker())->getBucketValueForUser("{$campaign->getId()}_{$userId}");
        $isUserPart = $valueAssignedToUser !== 0 && $valueAssignedToUser <= $trafficAllocation;
        
        LogManager::instance()->debug("User:{$userId} part of campaign {$campaign->getKey()} ? " . ($isUserPart ? 'true' : 'false'));

        return $isUserPart;
    }
    

    public function getVariation($variations, $bucketValue) {
        foreach ($variations as &$variation) {
            if ($bucketValue >= $variation->getStartRangeVariation() && $bucketValue <= $variation->getEndRangeVariation()) {
                return $variation;
            }
        }
        return null;
    }

    public function checkInRange($variation, $bucketValue) {
        if ($bucketValue >= $variation->getStartRangeVariation() && $bucketValue <= $variation->getEndRangeVariation()) {
            return $variation;
        }
    }

    public function bucketUserToVariation($userId, $accountId, $campaign) {
        if (!$campaign || !$userId) {
            return null;
        }
        $multiplier = $campaign->getTraffic() ? 1 : null;
        $percentTraffic = $campaign->getTraffic();
        $hashValue = (new DecisionMaker())->generateHashValue("{$campaign->getId()}_{$accountId}_{$userId}");
        $bucketValue = (new DecisionMaker())->generateBucketValue($hashValue, Constants::MAX_TRAFFIC_VALUE, $multiplier);
        LogManager::instance()->debug("user:{$userId} for campaign:{$campaign->getKey()} having percenttraffic:{$percentTraffic} got bucketValue as {$bucketValue} and hashvalue:{$hashValue}");
        $variaitons = $campaign->getVariations();
        return $this->getVariation( $variaitons, $bucketValue);
    }

    public function getDecision($campaign, $settings, $context) {
        $segments = [];
        if ($campaign->getType() === CampaignTypeEnum::ROLLOUT || $campaign->getType() === CampaignTypeEnum::PERSONALIZE) {
            $segments = $campaign->getVariations()[0]->getSegments();
        } else if ($campaign->getType() === CampaignTypeEnum::AB) {
            $segments = $campaign->getSegments();
        }
        if (DataTypeUtil::isObject($segments) && !count((array)$segments)) {
            LogManager::instance()->debug("For userId:{$context['user']['id']} of Campaign:{$campaign->getKey()}, segment was missing, hence skipping segmentation");
            return true;
        } else {
            $customVariables = isset($context['user']['customVariables']) ? $context['user']['customVariables'] : [];
            $preSegmentationResult = SegmentationManager::Instance()->validateSegmentation(
                $segments,
                $customVariables,
                $settings,
                $context['user']
                // array(
                //     'ipAddress' => $context['user']['ipAddress'],
                //     'userAgent' => $context['user']['userAgent']
                // )
            );
            if (!$preSegmentationResult) {
                LogManager::instance()->info("Segmentation failed for userId:{$context['user']['id']} of Campaign:{$campaign->getKey()}");
                return false;
            }
             LogManager::instance()->info("Segmentation passed for userId:{$context['user']['id']} of Campaign:{$campaign->getKey()}");
            return true;
        }
    }

    public function getVariationAlloted($userId, $accountId, $campaign) {
        $isUserPart = $this->isUserPartOfCampaign($userId, $campaign);
        
        if ($campaign->getType() === CampaignTypeEnum::ROLLOUT || $campaign->getType() === CampaignTypeEnum::PERSONALIZE) {
            return $isUserPart ? $campaign->getVariations()[0] : null;
        } else {
            return $isUserPart ? $this->bucketUserToVariation($userId, $accountId, $campaign) : null;
        }
    }    
}

?>
