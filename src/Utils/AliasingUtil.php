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

use vwo\Packages\Logger\Core\LogManager;
use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Services\SettingsService;
use vwo\Enums\UrlEnum;
use Exception;

/**
 * Utility class for handling alias operations through network calls to gateway
 */
class AliasingUtil
{
  private const KEY_USER_ID = 'userId';
  private const KEY_ALIAS_ID = 'aliasId';

  /**
   * Retrieves alias for a given user ID
   * @param string $userId The user identifier
   * @return mixed Returns response data array/object on success, or false on failure
   */
  public static function getAlias($userId)
  {
    try {
      $queryParams = [];
      $queryParams['accountId'] = SettingsService::instance()->accountId;
      $queryParams['sdkKey'] = SettingsService::instance()->sdkKey;
      // Backend expects userId as JSON array
      $queryParams[self::KEY_USER_ID] = json_encode([$userId]);

      $request = new RequestModel(
        SettingsService::instance()->hostname,
        'GET',
        UrlEnum::GET_ALIAS,
        $queryParams,
        null,
        null,
        SettingsService::instance()->protocol,
        SettingsService::instance()->port
      );

      $response = NetworkManager::instance()->get($request);
      if ($response) {
        $responseData = $response->getData();
        // alias id is the 0th index of the response data
        $aliasIdFromResponse = $responseData[0]->userId;
        return $aliasIdFromResponse;
      } else {
        return $userId;
      }
    } catch (Exception $err) {
      $message = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
      LogManager::instance()->error("Error occurred while fetching alias: {$message}");
      return false;
    }
  }

  /**
   * Sets alias for a given user ID
   * @param string $userId The user identifier
   * @param string $aliasId The alias identifier to set
   * @return mixed Returns response data array/object on success, or false on failure
   */
  public static function setAlias($userId, $aliasId)
  {
    try {
      $queryParams = [];
      $queryParams['accountId'] = SettingsService::instance()->accountId;
      $queryParams['sdkKey'] = SettingsService::instance()->sdkKey;
      $queryParams[self::KEY_USER_ID] = $userId;
      $queryParams[self::KEY_ALIAS_ID] = $aliasId;

      $requestBody = [
        self::KEY_USER_ID => $userId,
        self::KEY_ALIAS_ID => $aliasId,
      ];

      $request = new RequestModel(
        SettingsService::instance()->hostname,
        'POST',
        UrlEnum::SET_ALIAS,
        $queryParams,
        $requestBody,
        null,
        SettingsService::instance()->protocol,
        SettingsService::instance()->port
      );

      $response = NetworkManager::instance()->post($request);
      return $response ? $response->getData() : false;
    } catch (Exception $err) {
      $message = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
      LogManager::instance()->error("Error occurred while setting alias: {$message}");
      return false;
    }
  }
}

?>


