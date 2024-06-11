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
use vwo\Models\VariationModel as ModelsVariationModel;
use vwo\Utils\FunctionUtil;

class VariationModel
{
  public $id;
  public $key;
  public $name;
  public $weight;
  public $startRangeVariation;
  public $endRangeVariation;
  public $variables = [];
  public $variations = [];
  public $segments;

  public function modelFromDictionary($variation)
  {
    $variation = FunctionUtil::convertObjectToArray($variation);
    $this->id = isset($variation['i']) ? $variation['i'] : (isset($variation['id']) ? $variation['id'] : null);
    $this->key = isset($variation['n']) ? $variation['n'] : (isset($variation['key']) ? $variation['key'] : (isset($variation['name']) ? $variation['name'] : null));
    $this->name = isset($variation['n']) ? $variation['n'] : $variation['name'];
    $this->weight = isset($variation['w']) ? $variation['w'] : (isset($variation['weight']) ? $variation['weight'] : null);
    $this->setStartRange(isset($variation['startRangeVariation']) ? $variation['startRangeVariation'] : null);
    $this->setEndRange(isset($variation['endRangeVariation']) ? $variation['endRangeVariation'] : null);
    if (isset($variation['seg']) || isset($variation['segments'])) {
      $this->segments = isset($variation['seg']) ? $variation['seg'] : $variation['segments'];
    }

    if (isset($variation['variables'])) {
      if (is_array($variation['variables'])) {
        foreach ($variation['variables'] as $variable) {
          $this->variables[] = (new VariableModel())->modelFromDictionary($variable);
        }
      }
    }

    if (isset($variation['variations'])) {
      if (is_array($variation['variations'])) {
        foreach ($variation['variations'] as $var) {
          $this->variations[] = (new self())->modelFromDictionary($var);
        }
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

  public function getId() {
    return $this->id;
  }

  public function getKey() {
    return $this->key;
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
}
