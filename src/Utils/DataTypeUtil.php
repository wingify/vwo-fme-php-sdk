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

class DataTypeUtil {

    public static function isObject($val): bool {
        return is_array($val) || is_object($val);
    }

    public static function isArray($val): bool {
        return is_array($val);
    }

    public static function isNull($val): bool {
        return is_null($val);
    }

    public static function isNumber($val): bool {
        return is_numeric($val);
    }

    public static function isString($val): bool {
        return is_string($val);
    }

    public static function isBoolean($val): bool {
        return is_bool($val);
    }

    public static function getType($val): string {
        if (self::isObject($val)) {
            return 'Object';
        } elseif (self::isArray($val)) {
            return 'Array';
        } elseif (self::isNull($val)) {
            return 'Null';
        } elseif (self::isNumber($val)) {
            return 'Number';
        } elseif (self::isString($val)) {
            return 'String';
        } elseif (self::isBoolean($val)) {
            return 'Boolean';
        } else {
            return 'Unknown Type';
        }
    }
}

?>
