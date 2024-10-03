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

namespace vwo\Models;

use vwo\Models\VariableModel;

class VariationModel
{
    private $id;
    private $key;
    private $name;
    private $weight;
    private $startRangeVariation;
    private $endRangeVariation;
    private $variables = [];
    private $variations = [];
    private $segments;
    private $type;
    private $percentTraffic;
    private $isUserListEnabled;
    private $isForcedVariationEnabled;
    private $metrics = [];
    private $status;
    private $variationId;
    private $campaignId;
    private $ruleKey;

    public function modelFromDictionary($variation)
    {
        $this->id = isset($variation->i) ? $variation->i : (isset($variation->id) ? $variation->id : null);
        $this->key = isset($variation->n) ? $variation->n : (isset($variation->key) ? $variation->key : (isset($variation->name) ? $variation->name : null));
        $this->name = isset($variation->n) ? $variation->n : (isset($variation->name) ? $variation->name : null);
        $this->weight = isset($variation->w) ? $variation->w : (isset($variation->weight) ? $variation->weight : null);
        $this->startRangeVariation = isset($variation->startRangeVariation) ? $variation->startRangeVariation : null;
        $this->endRangeVariation = isset($variation->endRangeVariation) ? $variation->endRangeVariation : null;
        $this->segments = isset($variation->seg) ? $variation->seg : (isset($variation->segments) ? $variation->segments : null);
        $this->type = isset($variation->type) ? $variation->type : null;
        $this->percentTraffic = isset($variation->percentTraffic) ? $variation->percentTraffic : null;
        $this->isUserListEnabled = isset($variation->isUserListEnabled) ? $variation->isUserListEnabled : null;
        $this->isForcedVariationEnabled = isset($variation->isForcedVariationEnabled) ? $variation->isForcedVariationEnabled : null;
        $this->metrics = isset($variation->metrics) ? $variation->metrics : [];
        $this->status = isset($variation->status) ? $variation->status : null;
        $this->variationId = isset($variation->variationId) ? $variation->variationId : null;
        $this->campaignId = isset($variation->campaignId) ? $variation->campaignId : null;
        $this->ruleKey = isset($variation->ruleKey) ? $variation->ruleKey : null;
        

        if (isset($variation->variables)) {
            foreach ($variation->variables as $variable) {
                $this->variables[] = (new VariableModel())->modelFromDictionary($variable);
            }
        }

        if (isset($variation->variations)) {
            foreach ($variation->variations as $var) {
                $this->variations[] = (new self())->modelFromDictionary($var);
            }
        }

        return $this;
    }

    public function setStartRange($startRange) {
        $this->startRangeVariation = $startRange;
    }

    public function setEndRange($endRange) {
        $this->endRangeVariation = $endRange;
    }

    public function setWeight($weight) {
        $this->weight = $weight;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setPercentTraffic($percentTraffic) {
        $this->percentTraffic = $percentTraffic;
    }

    public function setIsUserListEnabled($isUserListEnabled) {
        $this->isUserListEnabled = $isUserListEnabled;
    }

    public function setIsForcedVariationEnabled($isForcedVariationEnabled) {
        $this->isForcedVariationEnabled = $isForcedVariationEnabled;
    }

    public function setMetrics($metrics) {
        $this->metrics = $metrics;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function setVariationId($variationId) {
        $this->variationId = $variationId;
    }

    public function setCampaignId($campaignId) {
        $this->campaignId = $campaignId;
    }

    public function setRuleKey($ruleKey) {
        $this->ruleKey = $ruleKey;
    }

    public function getId() {
        return $this->id;
    }

    public function getKey() {
        return $this->key;
    }

    public function getName() {
        return $this->name;
    }

    public function getWeight() {
        return $this->weight;
    }

    public function getSegments() {
        return $this->segments;
    }

    public function getStartRangeVariation() {
        return $this->startRangeVariation;
    }

    public function getEndRangeVariation() {
        return $this->endRangeVariation;
    }

    public function getVariables() {
        return $this->variables;
    }

    public function getVariations() {
        return $this->variations;
    }

    public function getType() {
        return $this->type;
    }

    public function getPercentTraffic() {
        return $this->percentTraffic;
    }

    public function getIsUserListEnabled() {
        return $this->isUserListEnabled;
    }

    public function getIsForcedVariationEnabled() {
        return $this->isForcedVariationEnabled;
    }

    public function getMetrics() {
        return $this->metrics;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getVariationId() {
        return $this->variationId;
    }

    public function getCampaignId() {
        return $this->campaignId;
    }

    public function getRuleKey() {
        return $this->ruleKey;
    }
}
