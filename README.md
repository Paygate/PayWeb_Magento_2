# PayWeb_Magento_2

## Paygate Magento plugin v2.6.0 for Magento v2.4.7

This is the Paygate PayWeb3 plugin for Magento 2. Please feel free to contact the Payfast support team at
support@payfast.help should you require any assistance.

## Installation

1. **Download the Plugin**

    - **Option 1: Automatic Installation**
        - Install the module using the following composer command:
        ```console
        composer require paygate/paygate-payweb-gateway
        ```
    - **Option 2: Manual Installation**
        - Visit the [releases page](https://github.com/Paygate/PayWeb_Magento_2/releases) and
          download [PayGate.zip](https://github.com/Paygate/PayHost_Magento_2/releases/download/v1.1.0/PayGate.zip).
        - Extract the contents of `PayGate.zip`, then upload the newly created **PayGate** directory into your Magento
          app/code directory (e.g. magentorootfolder/app/code/).

3. **Install the Plugin**

    - Run the following Magento CLI commands:
      ```console
      php bin/magento module:enable PayGate_PayWeb
      php bin/magento setup:upgrade
      php bin/magento setup:di:compile
      php bin/magento setup:static-content:deploy
      php bin/magento indexer:reindex
      php bin/magento cache:clean
      ```
4. **Configure the Plugin**

    - Login to the Magento admin panel.
    - Navigate to **Stores > Configuration > Sales > Payment Methods** and click on
      **Paygate PayWeb3**.
    - Configure the module according to your needs, then click the **Save Config** button.

## Collaboration

Please submit pull requests with any tweaks, features or fixes you would like to share.
