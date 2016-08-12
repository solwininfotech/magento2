<?php
/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Install\Test\TestCase;

use Magento\Install\Test\Page\Install;
use Magento\Install\Test\Page\Devdocs;
use Magento\Install\Test\Fixture\Install as InstallConfig;
use Magento\User\Test\Fixture\User;
use Magento\Mtf\Fixture\FixtureFactory;
use Magento\Mtf\TestCase\Injectable;
use Magento\Install\Test\Constraint\AssertAgreementTextPresent;
use Magento\Install\Test\Constraint\AssertSuccessfulReadinessCheck;
use Magento\Install\Test\Constraint\AssertAdminUriAutogenerated;
use Magento\Install\Test\Constraint\AssertDevdocsLink;
use Magento\Mtf\Util\Command\Cli\Setup;
use Magento\Mtf\Client\BrowserInterface;

/**
 * PLEASE ADD NECESSARY INFO BEFORE RUNNING TEST TO
 * ../dev/tests/functional/config/config.xml
 *
 * Preconditions:
 * 1. Uninstall Magento.
 *
 * Steps:
 * 1. Go setup landing page.
 * 2. Click on Developer Documentation link.
 * 3. Check  Developer Documentation title.
 * 4. Click on "Terms and agreements" button.
 * 5. Check license agreement text.
 * 6. Return back to landing page and click "Agree and Setup" button.
 * 7. Click "Start Readiness Check" button.
 * 8. Make sure PHP Version, PHP Extensions and File Permission are ok.
 * 9. Click "Next" and fill DB credentials.
 * 10. Click "Test Connection and Authentication" and make sure connection successful.
 * 11. Click "Next" and fill store address and admin path.
 * 12. Click "Next" and leave all default values.
 * 13. Click "Next" and fill admin user info.
 * 14. Click "Next" and on the "Step 6: Install" page click "Install Now" button.
 * 15. Perform assertions.
 *
 * @group Installer_and_Upgrade/Downgrade_(PS)
 * @ZephyrId MAGETWO-31431
 */
class InstallTest extends Injectable
{
    /**
     * Developer Documentation link text.
     */
    const DEVDOCS_LINK_TEXT = 'Getting Started';

    /**
     * Developer Documentation page.
     *
     * @var Devdocs
     */
    protected $devdocsPage;

    /**
     * Install page.
     *
     * @var Install
     */
    protected $installPage;

    /**
     * Uninstall Magento before test.
     *
     * @return array
     */
    public function __prepare()
    {
        $config = $this->objectManager->get(\Magento\Mtf\Config\DataInterface::class);
        // Prepare config data
        $configData['dbHost'] = $config->get('install/0/host/0');
        $configData['dbUser'] = $config->get('install/0/user/0');
        $configData['dbPassword'] = $config->get('install/0/password/0');
        $configData['dbName'] = $config->get('install/0/dbName/0');
        $configData['baseUrl'] = $config->get('install/0/baseUrl/0');
        $configData['admin'] = $config->get('install/0/backendName/0');

        return ['configData' => $configData];
    }

    /**
     * Uninstall Magento.
     *
     * @param Install $installPage
     * @param Setup $magentoSetup
     * @param Devdocs $devdocsPage
     * @return void
     */
    public function __inject(Install $installPage, Setup $magentoSetup, Devdocs $devdocsPage)
    {
        // Uninstall Magento.
        $magentoSetup->uninstall();
        $this->installPage = $installPage;
        $this->devdocsPage = $devdocsPage;
    }

    /**
     * Install Magento via web interface.
     *
     * @param User $user
     * @param array $configData
     * @param FixtureFactory $fixtureFactory
     * @param AssertAgreementTextPresent $assertLicense
     * @param AssertSuccessfulReadinessCheck $assertReadiness
     * @param AssertAdminUriAutogenerated $assertAdminUri
     * @param AssertDevdocsLink $assertDevdocsLink
     * @param BrowserInterface $browser
     * @param array $install [optional]
     * @return array
     */
    public function test(
        User $user,
        array $configData,
        FixtureFactory $fixtureFactory,
        AssertAgreementTextPresent $assertLicense,
        AssertSuccessfulReadinessCheck $assertReadiness,
        AssertAdminUriAutogenerated $assertAdminUri,
        AssertDevdocsLink $assertDevdocsLink,
        BrowserInterface $browser,
        array $install = []
    ) {
        $dataConfig = array_merge($install, $configData);
        if (isset($dataConfig['httpsFront'])) {
            $dataConfig['https'] = str_replace('http', 'https', $dataConfig['baseUrl']);
        }
        /** @var InstallConfig $installConfig */
        $installConfig = $fixtureFactory->create(\Magento\Install\Test\Fixture\Install::class, ['data' => $dataConfig]);
        // Steps
        $this->installPage->open();
        // Verify Developer Documentation link.
        $this->installPage->getLandingBlock()->clickDevdocsLink(self::DEVDOCS_LINK_TEXT);
        $browser->selectWindow();
        $assertDevdocsLink->processAssert($this->devdocsPage);
        $browser->closeWindow();
        // Verify license agreement.
        $this->installPage->getLandingBlock()->clickTermsAndAgreement();
        $assertLicense->processAssert($this->installPage);
        $this->installPage->getLicenseBlock()->clickBack();
        $this->installPage->getLandingBlock()->clickAgreeAndSetup();
        // Step 1: Readiness Check.
        $this->installPage->getReadinessBlock()->clickReadinessCheck();
        $assertReadiness->processAssert($this->installPage);
        $this->installPage->getReadinessBlock()->clickNext();
        // Step 2: Add a Database.
        $this->installPage->getDatabaseBlock()->fill($installConfig);
        $this->installPage->getDatabaseBlock()->clickNext();
        // Step 3: Web Configuration.
        $assertAdminUri->processAssert($this->installPage);
        $this->installPage->getWebConfigBlock()->clickAdvancedOptions();
        $this->installPage->getWebConfigBlock()->fill($installConfig);
        $this->installPage->getWebConfigBlock()->clickNext();
        // Step 4: Customize Your Store
        $this->installPage->getCustomizeStoreBlock()->fill($installConfig);
        $this->installPage->getCustomizeStoreBlock()->clickNext();
        // Step 5: Create Admin Account.
        $this->installPage->getCreateAdminBlock()->fill($user);
        $this->installPage->getCreateAdminBlock()->clickNext();
        // Step 6: Install.
        $this->installPage->getInstallBlock()->clickInstallNow();

        return ['installConfig' => $installConfig];
    }
}
