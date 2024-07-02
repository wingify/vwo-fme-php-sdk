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

class VariableModel {
  private $value;
  private $type;
  private $key;
  private $id;

  public function modelFromDictionary($variable) {
    $this->value = isset($variable->val) ? $variable->val : (isset($variable->value) ? $variable->value : null);
    $this->type = isset($variable->type) ? $variable->type : null;
    $this->key = isset($variable->k) ? $variable->k : (isset($variable->key) ? $variable->key : null);
    $this->id = isset($variable->i) ? $variable->i : (isset($variable->id) ? $variable->id : null);
    return $this;
  }

  public function setValue($value) {
    $this->value = $value;
  }

  public function setKey($key) {
    $this->key = $key;
  }

  public function setType($type) {
    $this->type = $type;
  }

  public function getId() {
    return $this->id;
  }

  public function getValue() {
    return $this->value;
  }

  public function getType() {
    return $this->type;
  }

  public function getKey() {
    return $this->key;
  }
}

?>
