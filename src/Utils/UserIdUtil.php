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

use vwo\Services\SettingsService;
use vwo\Services\LoggerService;
use vwo\Services\ServiceContainer;

class UserIdUtil
{
  /**
   * Resolves the canonical userId considering aliasing feature and gateway availability
   * @param string $userId
   * @param bool $isAliasingEnabled
   * @param ServiceContainer|null $serviceContainer
   * @return string
   */
  public static function getUserId($userId, $isAliasingEnabled, ServiceContainer $serviceContainer = null)
  {
    if ($isAliasingEnabled) {
      $settingsService = $serviceContainer ? $serviceContainer->getSettingsService() : SettingsService::instance();
      if ($settingsService->isGatewayServiceProvided) {
        $aliasId = AliasingUtil::getAlias($userId, $serviceContainer);
        return $aliasId;
      } else {
        return $userId;
      }
    } else {
      return $userId;
    }
  }
}

?>


