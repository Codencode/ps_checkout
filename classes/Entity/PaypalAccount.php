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

namespace PrestaShop\Module\PrestashopCheckout\Entity;

/**
 * Not really an entity.
 * Define and manage data regarding paypal account
 */
class PaypalAccount
{
    /**
     * Const list of databse fields used for store data
     */
    const PS_CHECKOUT_PAYPAL_ID_MERCHANT = 'PS_CHECKOUT_PAYPAL_ID_MERCHANT';
    const PS_CHECKOUT_PAYPAL_EMAIL_MERCHANT = 'PS_CHECKOUT_PAYPAL_EMAIL_MERCHANT';
    const PS_CHECKOUT_PAYPAL_EMAIL_STATUS = 'PS_CHECKOUT_PAYPAL_EMAIL_STATUS';
    const PS_CHECKOUT_PAYPAL_PAYMENT_STATUS = 'PS_CHECKOUT_PAYPAL_PAYMENT_STATUS';
    const PS_CHECKOUT_CARD_PAYMENT_STATUS = 'PS_CHECKOUT_CARD_PAYMENT_STATUS';

    /**
     * @var string
     */
    private $merchantId;

    /**
     * Email of the merchant
     *
     * @var string
     */
    private $email;

    /**
     * Status of the email, if it has been validated or not
     *
     * @var int
     */
    private $emailIsVerified;

    /**
     * Paypal payment method status
     *
     * @var int
     */
    private $paypalPaymentStatus;

    /**
     * Card payment method status
     *
     * @var string
     */
    private $cardPaymentStatus;

    public function __construct($merchantId = null, $email = null, $emailIsVerified = null, $paypalPaymentStatus = null, $cardPaymentStatus = null)
    {
        if (empty($merchantId)) {
            throw new \PrestaShopException('merchantId cannot be empty');
        }

        $this->setMerchantId($merchantId);
        $this->setEmail($email);
        $this->setEmailIsVerified($emailIsVerified);
        $this->setPaypalPaymentStatus($paypalPaymentStatus);
        $this->setCardPaymentStatus($cardPaymentStatus);
    }

    public function save()
    {
        return \Configuration::updateValue(self::PS_CHECKOUT_PAYPAL_ID_MERCHANT, $this->getMerchantId())
            && \Configuration::updateValue(self::PS_CHECKOUT_PAYPAL_EMAIL_MERCHANT, $this->getEmail())
            && \Configuration::updateValue(self::PS_CHECKOUT_PAYPAL_EMAIL_STATUS, $this->getEmailIsVerified())
            && \Configuration::updateValue(self::PS_CHECKOUT_PAYPAL_PAYMENT_STATUS, $this->getPaypalPaymentStatus())
            && \Configuration::updateValue(self::PS_CHECKOUT_CARD_PAYMENT_STATUS, $this->getCardPaymentStatus());
    }

    public function delete()
    {
        $this->setMerchantId('');
        $this->setEmail('');
        $this->setEmailIsVerified('');
        $this->setPaypalPaymentStatus('');
        $this->setCardPaymentStatus('');

        return $this->save();
    }

    /**
     * Getter for merchantId
     *
     * @return string
     */
    public function getMerchantId()
    {
        return $this->merchantId;
    }

    /**
     * Getter for email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Getter for emailVerified
     *
     * @return int
     */
    public function getEmailIsVerified()
    {
        return $this->emailIsVerified;
    }

    /**
     * Getter for paypalPaymentStatus
     *
     * @return int
     */
    public function getPaypalPaymentStatus()
    {
        return $this->paypalPaymentStatus;
    }

    /**
     * Getter for cardPaymentStatus
     *
     * @return string
     */
    public function getCardPaymentStatus()
    {
        return $this->cardPaymentStatus;
    }

    /**
     * Setter for merchantId
     *
     * @param string
     */
    public function setMerchantId($merchantId)
    {
        $this->merchantId = $merchantId;
    }

    /**
     * Setter for email
     *
     * @param string
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * Setter for emailVerified
     *
     * @param int
     */
    public function setEmailIsVerified($status)
    {
        $this->emailIsVerified = $status;
    }

    /**
     * Setter for paypalPaymentStatus
     *
     * @return int
     */
    public function setPaypalPaymentStatus($status)
    {
        $this->paypalPaymentStatus = $status;
    }

    /**
     * Setter for cardPaymentStatus
     *
     * @return string
     */
    public function setCardPaymentStatus($status)
    {
        $this->cardPaymentStatus = $status;
    }
}
