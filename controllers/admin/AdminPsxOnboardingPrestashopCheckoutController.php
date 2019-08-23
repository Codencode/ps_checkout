<?php
/**
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
use PrestaShop\Module\PrestashopCheckout\Store\StoreManager;

class AdminPsxOnboardingPrestashopCheckoutController extends ModuleAdminController
{
    public function init()
    {
        $storeManager = new StoreManager();

        $isLogout = (int) Tools::getValue('logout');

        // when logout
        if ($isLogout === 1) {
            $storeManager->unlinkPaypal();
            $storeManager->psxLogout();
            $storeManager->updatePsxAccount('', '', '', '');

            $this->redirectToModuleConfiguration();
        }

        $idToken = Tools::getValue('idToken');
        $refreshToken = Tools::getValue('refreshToken');
        $localId = Tools::getValue('localId');
        $email = Tools::getValue('email');

        if (empty($idToken)) {
            throw new PrestaShopException('idToken cannot be empty');
        }

        if (empty($refreshToken)) {
            throw new PrestaShopException('refreshToken cannot be empty');
        }

        if (empty($localId)) {
            throw new PrestaShopException('localId cannot be empty');
        }

        if (empty($email)) {
            throw new PrestaShopException('email cannot be empty');
        }

        $storeManager->updatePsxAccount($idToken, $refreshToken, $localId, $email);

        $this->redirectToModuleConfiguration();
    }

    /**
     * Redirect to the module configuration page
     */
    private function redirectToModuleConfiguration()
    {
        return Tools::redirect(
            $this->context->link->getAdminLink(
                'AdminModules',
                true,
                array(),
                array(
                    'configure' => 'ps_checkout',
                )
            )
        );
    }
}
