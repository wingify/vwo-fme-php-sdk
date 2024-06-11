# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

[1.0.0] - 2024-06-11

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
