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

class MetricModel {
  private $key;
  private $identifier;
  private $id;
  private $type;

  public function modelFromDictionary($metric) {
    $this->identifier = isset($metric->identifier) ? $metric->identifier : $metric->key;
    $this->id = isset($metric->i) ? $metric->i : $metric->id;
    $this->type = isset($metric->t) ? $metric->t : $metric->type;
    return $this;
  }

  public function getId() {
    return $this->id;
  }

  public function getIdentifier() {
    return $this->identifier;
  }

  public function getType() {
    return $this->type;
  }
}

?>
