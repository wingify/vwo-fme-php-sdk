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

use vwo\Packages\NetworkLayer\Manager\NetworkManager;
use vwo\Packages\NetworkLayer\Models\RequestModel;
use vwo\Services\SettingsService;
use vwo\Enums\UrlEnum;
use Exception;
use vwo\Services\ServiceContainer;

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
   * @param ServiceContainer|null $serviceContainer
   * @return mixed Returns response data array/object on success, or false on failure
   */
  public static function getAlias($userId, ServiceContainer $serviceContainer = null)
  {
    try {
      $settingsService = $serviceContainer ? $serviceContainer->getSettingsService() : SettingsService::instance();
      $queryParams = [];
      $queryParams['accountId'] = $settingsService->accountId;
      $queryParams['sdkKey'] = $settingsService->sdkKey;
      // Backend expects userId as JSON array
      $queryParams[self::KEY_USER_ID] = json_encode([$userId]);

      $request = new RequestModel(
        $settingsService->hostname,
        'GET',
        UrlEnum::GET_ALIAS,
        $queryParams,
        null,
        null,
        $settingsService->protocol,
        $settingsService->port
      );

      $networkManager = $serviceContainer ? $serviceContainer->getNetworkManager() : NetworkManager::instance();
      $response = $networkManager->get($request);
      if ($response) {
        $responseData = $response->getData();
        // Check if response data exists and has the expected structure
        if ($responseData && is_array($responseData) && isset($responseData[0]) && is_object($responseData[0]) && isset($responseData[0]->userId)) {
          $aliasIdFromResponse = $responseData[0]->userId;
          return $aliasIdFromResponse;
        } else {
          if ($serviceContainer) {
            $serviceContainer->getLogManager()->debug("No response from the gateway or the call to the gateway failed.");
          }
          return $userId;
        }
      } else {
        if ($serviceContainer) {
          $serviceContainer->getLogManager()->debug("No response from the gateway or the call to the gateway failed.");
        }
        return $userId;
      }
    } catch (Exception $err) {
      $message = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';

      $logManager = $serviceContainer->getLogManager();
      $logManager->error("Error occurred while fetching alias: {$message}");
      return $userId;
    }
  }

  /**
   * Sets alias for a given user ID
   * @param string $userId The user identifier
   * @param string $aliasId The alias identifier to set
   * @param ServiceContainer|null $serviceContainer
   * @return mixed Returns response data array/object on success, or false on failure
   */
  public static function setAlias($userId, $aliasId, ServiceContainer $serviceContainer = null)
  {
    try {
      $settingsService = $serviceContainer ? $serviceContainer->getSettingsService() : SettingsService::instance();
      $queryParams = [];
      $queryParams['accountId'] = $settingsService->accountId;
      $queryParams['sdkKey'] = $settingsService->sdkKey;
      $queryParams[self::KEY_USER_ID] = $userId;
      $queryParams[self::KEY_ALIAS_ID] = $aliasId;

      $requestBody = [
        self::KEY_USER_ID => $userId,
        self::KEY_ALIAS_ID => $aliasId,
      ];

      $request = new RequestModel(
        $settingsService->hostname,
        'POST',
        UrlEnum::SET_ALIAS,
        $queryParams,
        $requestBody,
        null,
        $settingsService->protocol,
        $settingsService->port
      );

      $networkManager = $serviceContainer ? $serviceContainer->getNetworkManager() : NetworkManager::instance();
      $response = $networkManager->post($request);
      return $response ? $response->getData() : false;
    } catch (Exception $err) {
      $message = $err instanceof \Exception ? $err->getMessage() : 'Unknown error';
      $logManager = $serviceContainer->getLogManager();
      $logManager->error("Error occurred while setting alias: {$message}");
      return false;
    }
  }
}

?>


