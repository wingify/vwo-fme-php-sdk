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

class RuleModel {
    private $status;
    private $variationId;
    private $campaignId;
    private $type;
    private $ruleKey;

    public function modelFromDictionary($rule) {
        $this->type = isset($rule->type) ? $rule->type : null;
        $this->status = isset($rule->status) ? $rule->status : null;
        $this->variationId = isset($rule->variationId) ? $rule->variationId : null;
        $this->campaignId = isset($rule->campaignId) ? $rule->campaignId : null;
        $this->ruleKey = isset($rule->ruleKey) ? $rule->ruleKey : null;
        return $this;
    }

    public function getCampaignId() {
        return $this->campaignId;
    }

    public function getVariationId() {
        return $this->variationId;
    }

    public function getStatus() {
        return $this->status;
    }

    public function getType() {
        return $this->type;
    }

    public function getRuleKey() {
        return $this->ruleKey;
    }

    public function setCampaignId($campaignId) {
        $this->campaignId = $campaignId;
    }

    public function setVariationId($variationId) {
        $this->variationId = $variationId;
    }

    public function setStatus($status) {
        $this->status = $status;
    }

    public function setType($type) {
        $this->type = $type;
    }

    public function setRuleKey($ruleKey) {
        $this->ruleKey = $ruleKey;
    }

}

?>
