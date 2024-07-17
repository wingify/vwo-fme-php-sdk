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

class SegmentOperandValueEnum {
    public const LOWER_VALUE = 1;
    public const STARTING_ENDING_STAR_VALUE = 2;
    public const STARTING_STAR_VALUE = 3;
    public const ENDING_STAR_VALUE = 4;
    public const REGEX_VALUE = 5;
    public const EQUAL_VALUE = 6;
    public const GREATER_THAN_VALUE = 7;
    public const GREATER_THAN_EQUAL_TO_VALUE = 8;
    public const LESS_THAN_VALUE = 9;
    public const LESS_THAN_EQUAL_TO_VALUE = 10;
}

?>
