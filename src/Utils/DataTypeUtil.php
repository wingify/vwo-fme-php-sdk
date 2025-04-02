<?php

/**
 * Copyright 2024-2025 Wingify Software Pvt. Ltd.
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

    /**
     * Checks if a value is an object excluding arrays, functions, regexes, promises, and dates.
     * @param mixed $val The value to check.
     * @return bool True if the value is an object, false otherwise.
     */
    public static function isObject($val) {
        //return gettype($val) === 'object' && !self::isArray($val) && !self::isFunction($val) && !self::isRegex($val) && !self::isPromise($val) && !self::isDate($val);
        return is_array($val) || is_object($val);
    }

    /**
     * Checks if a value is an array.
     * @param mixed $val The value to check.
     * @return bool True if the value is an array, false otherwise.
     */
    public static function isArray($val) {
        return gettype($val) === 'array';
    }

    /**
     * Checks if a value is null.
     * @param mixed $val The value to check.
     * @return bool True if the value is null, false otherwise.
     */
    public static function isNull($val) {
        return gettype($val) === 'NULL';
    }

    /**
     * Checks if a value is undefined.
     * @param mixed $val The value to check.
     * @return bool True if the value is undefined, false otherwise.
     */
    public static function isUndefined($val) {
        return gettype($val) === 'undefined';
    }

    /**
     * Checks if a value is defined, i.e., not undefined and not null.
     * @param mixed $val The value to check.
     * @return bool True if the value is defined, false otherwise.
     */
    public static function isDefined($val) {
        return !self::isUndefined($val) && !self::isNull($val);
    }

    /**
     * Checks if a value is a number, including NaN.
     * @param mixed $val The value to check.
     * @return bool True if the value is a number, false otherwise.
     */
    public static function isNumber($val) {
        return gettype($val) === 'double' || gettype($val) === 'integer';
    }

    /**
     * Checks if a value is a string.
     * @param mixed $val The value to check.
     * @return bool True if the value is a string, false otherwise.
     */
    public static function isString($val) {
        return gettype($val) === 'string';
    }

    /**
     * Checks if a value is a boolean.
     * @param mixed $val The value to check.
     * @return bool True if the value is a boolean, false otherwise.
     */
    public static function isBoolean($val) {
        return gettype($val) === 'boolean';
    }

    /**
     * Checks if a value is NaN.
     * @param mixed $val The value to check.
     * @return bool True if the value is NaN, false otherwise.
     */
    public static function isNaN($val) {
        return is_nan($val);
    }

    /**
     * Checks if a value is a Date object.
     * @param mixed $val The value to check.
     * @return bool True if the value is a Date object, false otherwise.
     */
    public static function isDate($val) {
        return gettype($val) === 'object' && get_class($val) === 'DateTime';
    }

    /**
     * Checks if a value is a function.
     * @param mixed $val The value to check.
     * @return bool True if the value is a function, false otherwise.
     */
    public static function isFunction($val) {
        return gettype($val) === 'object' && ($val instanceof \Closure);
    }

    /**
     * Checks if a value is a regular expression.
     * @param mixed $val The value to check.
     * @return bool True if the value is a regular expression, false otherwise.
     */
    public static function isRegex($val) {
        return gettype($val) === 'object' && get_class($val) === 'Regex';
    }

    /**
     * Checks if a value is a Promise.
     * @param mixed $val The value to check.
     * @return bool True if the value is a Promise, false otherwise.
     */
    public static function isPromise($val) {
        return gettype($val) === 'object' && method_exists($val, 'then');
    }

    /**
     * Determines the type of the given value using various type-checking utility functions.
     * @param mixed $val The value to determine the type of.
     * @return string A string representing the type of the value.
     */
    public static function getType($val): string {
        if (self::isObject($val)) {
            return 'Object';
        } elseif (self::isArray($val)) {
            return 'Array';
        } elseif (self::isNull($val)) {
            return 'Null';
        } elseif (self::isUndefined($val)) {
            return 'Undefined';
        } elseif (self::isNaN($val)) {
            return 'NaN';
        } elseif (self::isNumber($val)) {
            return 'Number';
        } elseif (self::isString($val)) {
            return 'String';
        } elseif (self::isBoolean($val)) {
            return 'Boolean';
        } elseif (self::isDate($val)) {
            return 'Date';
        } elseif (self::isRegex($val)) {
            return 'Regex';
        } elseif (self::isFunction($val)) {
            return 'Function';
        } elseif (self::isPromise($val)) {
            return 'Promise';
        } else {
            return 'Unknown Type';
        }
    }
}

?>