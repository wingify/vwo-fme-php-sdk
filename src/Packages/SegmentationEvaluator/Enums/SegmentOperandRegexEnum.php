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

namespace vwo\Packages\SegmentationEvaluator\Enums;

class SegmentOperandRegexEnum {
    const LOWER = '/^lower/';
    const LOWER_MATCH = '/^lower\((.*)\)/';
    const WILDCARD = '/^wildcard/';
    const WILDCARD_MATCH = '/^wildcard\((.*)\)/';
    const STARTING_STAR = '/^\*/';
    const ENDING_STAR = '/\*$/';
    const REGEX = '/^regex/';
    const REGEX_MATCH = '/^regex\((.*)\)/';
    const GREATER_THAN = '/^gt\(((\d+\.?\d*)|(\.\d+))\)/';
    const LESS_THAN = '/^lt\(((\d+\.?\d*)|(\.\d+))\)/';
    const GREATER_THAN_EQUAL_TO = '/^gte\(((\d+\.?\d*)|(\.\d+))\)/';
    const LESS_THAN_EQUAL_TO = '/^lte\(((\d+\.?\d*)|(\.\d+))\)/';
    const IN_LIST = '/inlist\(([^)]+)\)/';
}
?>
