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

use vwo\Utils\DataTypeUtil as DataTypeUtil;

/**
 * Extracts the first key-value pair from the provided object.
 * @param array $obj - The object from which to extract the key-value pair.
 * @return array|null An array containing the first key and value, or null if the input is not an object.
 */
function getKeyValue($obj): ?array {
    // Check if the input is a valid object using isObject utility function
    if (!DataTypeUtil::isObject($obj)) {
        return null;
    }

    // Extract the first key from the object
    $key = array_key_first($obj);
    // Retrieve the value associated with the first key
    $value = $obj[$key];
    // Return an array containing the key and value
    return [
        'key' => $key,
        'value' => $value
    ];
}

/**
 * Matches a string against a regular expression and returns the match result.
 * @param string $string - The string to match against the regex.
 * @param string $regex - The regex pattern as a string.
 * @return array|null The results of the regex match, or null if an error occurs.
 */
function matchWithRegex($string, $regex): ?array {
    try {
        // Attempt to match the string with the regex
        preg_match('/' . $regex . '/', $string, $matches);
        return $matches;
    } catch (\Exception $err) {
        // Return null if an error occurs during regex matching
        return null;
    }
}

?>
