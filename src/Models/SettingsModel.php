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

use vwo\Models\FeatureModel;
use vwo\Models\CampaignModel;
use vwo\Utils\FunctionUtil;

class SettingsModel {
  public $sdkKey;
  public $features = [];
  public $campaigns = [];
  public $campaignGroups = [];
  public $groups = [];
  public $accountId;
  public $version;
  public $collectionPrefix;

  public function __construct($settings) {
    $this->sdkKey = isset($settings['sK']) ? $settings['sK'] : (isset($settings['sdkKey']) ? $settings['sdkKey'] : null);
    $this->accountId = isset($settings['a']) ? $settings['a'] : (isset($settings['accountId']) ? $settings['accountId'] : null);
    $this->version = isset($settings['v']) ? $settings['v'] : (isset($settings['version']) ? $settings['version'] : null);
    $this->collectionPrefix = isset($settings['collectionPrefix']) ? $settings['collectionPrefix'] : null;

    if (isset($settings['f']) && is_array($settings['f'])) {
      $featureList = $settings['f'];
      foreach ($featureList as $feature) {
        $this->features[] = (new FeatureModel())->modelFromDictionary($feature);
      }
    } elseif (isset($settings['features']) && is_array($settings['features'])) {
      $featureList = $settings['features'];
      foreach ($featureList as $feature) {
        $this->features[] = (new FeatureModel())->modelFromDictionary($feature);
      }
    }

    if (isset($settings['c']) && is_array($settings['c'])) {
      $campaignList = $settings['c'];
      foreach ($campaignList as $campaign) {
        $this->campaigns[] = (new CampaignModel())->modelFromDictionary($campaign);
      }
    } elseif (isset($settings['campaigns']) && is_array($settings['campaigns'])) {
      $campaignList = $settings['campaigns'];
      foreach ($campaignList as $campaign) {
        $this->campaigns[] = (new CampaignModel())->modelFromDictionary($campaign);
      }
    }

    $this->campaignGroups = isset($settings['cG']) ? $settings['cG'] : (isset($settings['campaignGroups']) ? $settings['campaignGroups'] : []);
    $this->groups = isset($settings['g']) ? $settings['g'] : (isset($settings['groups']) ? $settings['groups'] : []);
  }

  public function getFeatures() {
    return $this->features;
  }

  public function getCampaigns() {
    return $this->campaigns;
  }

  public function getSdkkey() {
    return $this->sdkKey;
  }

  public function getAccountId() {
    return $this->accountId;
  }

  public function getVersion() {
    return $this->version;
  }

  public function getCollectionPrefix() {
    return $this->collectionPrefix;
  }

  public function getCampaignGroups() {
    return $this->campaignGroups;
  }

  public function getGroups() {
    return $this->groups;
  }
}
