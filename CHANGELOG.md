# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.18.0] - 2026-02-05

### Added

- Added session management capabilities to enable integration with VWO's web client testing campaigns. The SDK now automatically generates and manages session IDs to connect server-side feature flag decisions with client-side user sessions.

  Example usage:

  ```php
  use vwo\VWO;

  $options = [
      'sdkKey' => '32-alpha-numeric-sdk-key',
      'accountId' => '123456',
  ];

  $vwoClient = VWO::init($options);

  // Session ID is automatically generated if not provided
  $context = ['id' => 'user-123'];
  $flag = $vwoClient->getFlag('feature-key', $context);

  // Access the session ID to pass to web client for session recording
  $sessionId = $flag->getSessionId();
  echo "Session ID for web client: " . $sessionId;
  ```

  You can also explicitly set a session ID to match a web client session:

  ```php
  use vwo\VWO;

  $vwoClient = VWO::init($options);

  $userContext = [
      'id' => 'user-123',
      'sessionId' => 1697123456  // Custom session ID matching web client
  ];

  $flag = $vwoClient->getFlag('feature-key', $userContext);
  ```

  This enhancement enables seamless integration between server-side feature flag decisions and client-side session recording, allowing for comprehensive user behavior analysis across both server and client environments.

## [1.17.0] - 2026-01-09

### Added

- Added support for configurable ANSI color output in logging. ANSI colors in logging are now only applied when `isAnsiColorEnabled` is set to `true` in the logger configuration.

Example:

```php
$vwoClient = VWO::init([
    'accountId' => '123456',
    'sdkKey' => '32-alpha-numeric-sdk-key',
    'logger' => [
        'level' => 'DEBUG',
        'isAnsiColorEnabled' => true, // Enable colored log levels in terminal
    ],
]);
```

- Added support for configurable settings expiry and network timeout through `settingsConfig` option. The SDK now allows customization of settings cache expiration and network timeout for settings fetch requests.

Example:

```php
$vwoClient = VWO::init([
    'accountId' => '123456',
    'sdkKey' => '32-alpha-numeric-sdk-key',
    'settingsConfig' => [
        'timeout' => 50000,       // Network timeout for settings fetch in milliseconds (default: 50000)
    ],
]);
```

## [1.16.0] - 2025-12-12

### Fixed

- Fixed singleton class issue where single service instance was being used across multiple sdk instances.

## [1.15.0] - 2025-12-10

### Added

- Added batch event processing to optimize network calls during GetFlag operations. Multiple impression events are now collected and sent in a single batch request

## [1.14.0] - 2025-11-26

### Fixed

- Fixed GET requests (settings calls) to always use cURL instead of sockets for improved reliability.


## [1.13.0] - 2025-10-10

### Added

- Introduced option to send network calls synchronously with the introduction of a new parameter in init, `shouldWaitForTrackingCalls`.
- Added `retryConfig` init parameter to configure retries: `shouldRetry`, `maxRetries`, `initialDelay`, `backoffMultiplier`.

Example:

```php
$vwoClient = VWO::init([
    'accountId' => '123456',
    'sdkKey' => '32-alpha-numeric-sdk-key',
    'shouldWaitForTrackingCalls' => true, // switch to synchronous (cURL) tracking
    'retryConfig' => [
        'shouldRetry' => true,        // default: true
        'maxRetries' => 3,            // default: 3
        'initialDelay' => 2,          // seconds; default: 2
        'backoffMultiplier' => 2,     // delays: 2s, 4s, 8s; default: 2
    ],
]);

// If you want synchronous calls without retries
$vwoClient = VWO::init([
    'accountId' => '123456',
    'sdkKey' => '32-alpha-numeric-sdk-key',
    'shouldWaitForTrackingCalls' => true,
    'retryConfig' => [
        'shouldRetry' => false,
    ],
]);
```

## [1.12.0] - 2025-09-25

### Added

- Add support for user aliasing (will work with [Gateway Service](https://developers.vwo.com/v2/docs/gateway-service) only)

```php
$vwoClient = VWO::init([
    'accountId' => 'vwo_account_id',
    'sdkKey' => '32-alpha-numeric-sdk-key',
    'gatewayService' => [
        'url' => 'http://your-custom-gateway-url',
    ],
    'isAliasingEnabled' => true,
]);

$vwoClient->setAlias($context, 'aliasId');
// alternatively you can also pass the userId and the aliasId instead of context and aliasId
$vwoClient->setAlias('userId', 'aliasId');
```

## [1.11.1] - 2025-09-12

### Fixed

- Implemented schema validation fixes and improvements

## [1.11.0] - 2025-09-02

### Added

- Post-segmentation variables are now automatically included as unregistered attributes, enabling post-segmentation without requiring manual setup.
- Added support for built-in targeting conditions, including browser version, OS version, and IP address, with advanced operator support (greaterThan, lessThan, regex).

## [1.10.0] - 2025-09-02

### Added

- Sends usage statistics to VWO servers automatically during SDK initialization

## [1.9.1] - 2025-08-06

### Fixed

- Fixed compatibility for PHP 7.0 version.

## [1.9.0] - 2025-08-05

### Added

- Added support for sending a one-time initialization event to the server to verify correct SDK setup.
- Added support for sending error logs to VWO server for better debugging.

## [1.8.1] - 2025-07-24

### Added

- Send the SDK name and version in the settings call to VWO as query parameters.


## [1.8.0] - 2025-07-01

### Changed

- Replaced `GuzzleHttp` with native PHP socket implementation for improved network performance.
- Achieved 90% reduction in `getFlag` API response time through optimized socket-based impression calls.

## [1.7.6] - 2025-05-28

### Added

- Support for VWO Internal Debugger

## [1.7.5] - 2025-05-13

### Added

- Added a feature to track and collect usage statistics related to various SDK features and configurations which can be useful for analytics, and gathering insights into how different features are being utilized by end users.

## [1.7.2] - 2025-05-06

### Fixed

- Update option name for passing `settings` while initializing the SDK.

## [1.7.1] - 2025-04-25

### Fixed

- Fixed the base URL issue for `EU` and `Asia` regions.

## [1.7.0] - 2024-04-14

### Added

- Lowered minimum PHP version requirement from 7.4 to 7.0

## [1.6.0] - 2024-04-09

### Added

- Support for `Map` in `setAttribute` method to send multiple attributes data.

## [1.5.0] - 2025-03-24

### Fixed

- Fixed the issue where the base URL was not being set correctly for `EU` and `Asia` regions.

## [1.4.0] - 2024-12-20

### Added

- Added the support for using salt for bucketing if provided in the rule configuration.


## [1.3.1] - 2024-10-04

### Fixed

- Improved Pre-segmentation result comparison logic to handle numeric values more accurately by trimming trailing zeroes and decimal points across various operand types


## [1.3.0] - 2024-10-03

### Added

- **Personalize MEG** - Add support for Personalize rules within `Mutually Exclusive Groups`

- **Segmentation module**

  - Modify how context and settings are being used inside modular segmentor code
  - Cache location / User-Agent data per `getFlag` API
  - Single endpoint for location and User-Agent at gateway-service so that at max one call will be required to fetch data from gateway service
  - If userAgent and ipAddress both are null, then no need to send call to gatewayService
  - Add support for DSL where featureIdValue could be `off`

- **Context refactoring** - context is now flattened out

    ```php
    [
        'id' => $userId,
        'ipAddress' => '',
        'userAgent' => '',
        // For pre-segmentation in campaigns
        'customVariables' => [ 'variable' => 'value' ]
    ]
    ```

- **Code refactoring** - source code is refactored to have error-handling, model-driven code, and inline documentation.

## [1.2.5] - 2024-07-29

### Fixed

- Removed unnecessary vendor/autoload from `UuidUtil.php`

## [1.2.1] - 2024-07-17

### Added

- Support for optional parameter Settings in init
- Support for gt,gte,lt,lte in Custom Variable pre-segmentation


## [1.1.1] - 2024-07-04

### Changed

- **Testing** - PHPUnit version changed to support in lower php versions


## [1.1.0] - 2024-07-02

### Changed

- **Refactoring**

    - Redesigned models to use private properties instead of public properties.
    - Implemented object notation with getter and setter functions for all models.

- **Unit and E2E Testing**

    - Wrote unit and E2E tests to ensure nothing breaks while pushing new code
    - Ensure critical components are working properly on every build


## [1.0.0] - 2024-06-11

### Added

- First release of VWO Feature Management and Experimentation capabilities.

    ```php
    use vwo\VWO;

    $vwoClient = VWO::init([
        'sdkKey' => 'vwo_sdk_key',
        'accountId' => 'vwo_account_id',
    ]);

    // set user context
    $userContext = [ 'id' => 'unique_user_id'];

    // returns a flag object
    $getFlag = $vwoClient->getFlag('feature_key', $userContext);

    // check if flag is enabled
    $isFlagEnabled = $getFlag['isEnabled'];

    // get variable
    $variableValue = $getFlag->getVariable('stringVar', 'default-value');

    // track event
    $trackRes = $vwoClient->trackEvent('event-name', $userContext);

    // set Attribute
    $setAttribute = $vwoClient->setAttribute('attribute-name', 'attribute-value', $userContext);

    ```
