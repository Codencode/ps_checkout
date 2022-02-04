<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

use Monolog\Logger;
use PrestaShop\Module\PrestashopCheckout\Api\Payment\Webhook as PaymentWebhook;
use PrestaShop\Module\PrestashopCheckout\Api\Psl\Webhook as PslWebhook;
use PrestaShop\Module\PrestashopCheckout\Context\PrestaShopContext;
use PrestaShop\Module\PrestashopCheckout\Controller\AbstractFrontController;
use PrestaShop\Module\PrestashopCheckout\Dispatcher\OrderDispatcher;
use PrestaShop\Module\PrestashopCheckout\Dispatcher\ShopDispatcher;
use PrestaShop\Module\PrestashopCheckout\Exception\PsCheckoutException;
use PrestaShop\Module\PrestashopCheckout\WebHookValidation;

/**
 * @todo To be refactored
 */
class ps_checkoutDispatchWebHookModuleFrontController extends AbstractFrontController
{
    const PS_CHECKOUT_PAYPAL_ID_LABEL = 'PS_CHECKOUT_PAYPAL_ID_MERCHANT';

    const CATEGORY = [
        'SHOP' => 'SHOP',
    ];

    /**
     * @var Ps_checkout
     */
    public $module;

    /**
     * @var bool If set to true, will be redirected to authentication page
     */
    public $auth = false;

    /**
     * UUID coming from PSL
     *
     * @var string
     */
    private $shopId;

    /**
     * Id coming from Paypal
     *
     * @var string
     */
    private $merchantId;

    /**
     * Id coming from Firebase
     *
     * @var int
     */
    private $firebaseId;

    /**
     * Get all the HTTP body values
     *
     * @var array
     */
    private $payload;

    /**
     * Initialize the webhook script
     *
     * @return bool
     */
    public function display()
    {
        try {
            $headerValues = $this->getHeaderValues();
            $validationValues = new WebHookValidation();

            $this->setAtributesHeaderValues($headerValues);

            $bodyContent = file_get_contents('php://input');

            if (empty($bodyContent)) {
                throw new PsCheckoutException('Body can\'t be empty', PsCheckoutException::PSCHECKOUT_WEBHOOK_BODY_EMPTY);
            }

            $bodyValues = json_decode($bodyContent, true);

            if (empty($bodyValues)) {
                throw new PsCheckoutException('Body can\'t be empty', PsCheckoutException::PSCHECKOUT_WEBHOOK_BODY_EMPTY);
            }

            $validationValues->validateBodyDatas($bodyValues);

            if (false === $this->checkPSLSignature($bodyValues)) {
                throw new PsCheckoutException('Invalid PSL signature', PsCheckoutException::PSCHECKOUT_WEBHOOK_PSL_SIGNATURE_INVALID);
            }

            $this->setAtributesBodyValues($bodyValues);

            return $this->dispatchWebHook();
        } catch (Exception $exception) {
            $this->handleException($exception);
        }

        return false;
    }

    /**
     * Check if the Webhook comes from the PSL
     *
     * @param array $bodyValues
     *
     * @return bool
     */
    private function checkPSLSignature(array $bodyValues)
    {
        $context = Context::getContext();

        if ($bodyValues['category'] === self::CATEGORY['SHOP']) {
            $webhook = new PslWebhook(new PrestaShopContext());
        } else {
            $webhook = new PaymentWebhook($context->link);
        }

        $response = $webhook->getShopSignature($bodyValues);

        return isset($response['status']) && $response['status'];
    }

    /**
     * Get HTTP Headers
     *
     * @return array
     */
    private function getHeaderValues()
    {
        // Not available on nginx
        if (function_exists('getallheaders')) {
            $headers = getallheaders();

            // Ensure we will not return empty values if Request is FORWARDED
            if (false === empty($headers['Shop-Id'])
                && false === empty($headers['Merchant-Id'])
                && false === empty($headers['Psx-Id'])
            ) {
                return [
                    'Shop-Id' => $headers['Shop-Id'],
                    'Merchant-Id' => $headers['Merchant-Id'],
                    'Psx-Id' => $headers['Psx-Id'],
                ];
            }
        }

        return [
            'Shop-Id' => isset($_SERVER['HTTP_SHOP_ID']) ? $_SERVER['HTTP_SHOP_ID'] : null,
            'Merchant-Id' => isset($_SERVER['HTTP_MERCHANT_ID']) ? $_SERVER['HTTP_MERCHANT_ID'] : null,
            'Psx-Id' => isset($_SERVER['HTTP_PSX_ID']) ? $_SERVER['HTTP_PSX_ID'] : null,
        ];
    }

    /**
     * Set Header Attributes values from the HTTP request
     *
     * @param array $headerValues
     */
    private function setAtributesHeaderValues(array $headerValues)
    {
        // TODO add a fallback ?? $this->getShopId(), ..., $this->getPsxId()
        $this->shopId = $headerValues['Shop-Id'];
        $this->merchantId = $headerValues['Merchant-Id'];
        $this->firebaseId = $headerValues['Psx-Id'];
    }

    /**
     * Set Body Attributes values from the payload
     *
     * @param array $bodyValues
     */
    private function setAtributesBodyValues(array $bodyValues)
    {
        $resource = $bodyValues['category'] === self::CATEGORY['SHOP']
            ? $bodyValues['resource']
            : json_decode($bodyValues['resource'], true);
        $this->payload = [
            'resource' => (array) $resource,
            'eventType' => isset($bodyValues['eventType']) ? (string) $bodyValues['eventType'] : '',
            'category' => (string) $bodyValues['category'],
            'summary' => isset($bodyValues['summary']) ? (string) $bodyValues['summary'] : '',
            'orderId' => isset($bodyValues['orderId']) ? (string) $bodyValues['orderId'] : '',
        ];
    }

    /**
     * Dispatch the web Hook according to the category
     *
     * @return bool
     */
    private function dispatchWebHook()
    {
        $this->module->getLogger()->info(
            'DispatchWebHook',
            [
                'merchantId' => $this->merchantId,
                'shopId' => $this->shopId,
                'firebaseId' => $this->firebaseId,
                'payload' => $this->payload,
            ]
        );

        if (self::CATEGORY['SHOP'] === $this->payload['category']) {
            return (new ShopDispatcher())->dispatchEventType($this->payload);
        }

        if ('ShopNotificationMerchantAccount' === $this->payload['category']) {
            return true;
        }

        if ('ShopNotificationOrderChange' === $this->payload['category']) {
            return (new OrderDispatcher())->dispatchEventType($this->payload);
        }

        $this->module->getLogger()->info(
            'DispatchWebHook ignored',
            [
                'merchantId' => $this->merchantId,
                'shopId' => $this->shopId,
                'firebaseId' => $this->firebaseId,
                'payload' => $this->payload,
            ]
        );

        return true;
    }

    /**
     * Override displayMaintenancePage to prevent the maintenance page to be displayed
     *
     * @see FrontController::displayMaintenancePage()
     */
    protected function displayMaintenancePage()
    {
        return;
    }

    /**
     * Override displayRestrictedCountryPage to prevent page country is not allowed
     *
     * @see FrontController::displayRestrictedCountryPage()
     */
    protected function displayRestrictedCountryPage()
    {
        return;
    }

    /**
     * Override geolocationManagement to prevent country GEOIP blocking
     *
     * @see FrontController::geolocationManagement()
     *
     * @param Country $defaultCountry
     *
     * @return false
     */
    protected function geolocationManagement($defaultCountry)
    {
        return false;
    }

    /**
     * Override sslRedirection to prevent redirection
     *
     * @see FrontController::sslRedirection()
     */
    protected function sslRedirection()
    {
        return;
    }

    /**
     * Override canonicalRedirection to prevent redirection
     *
     * @see FrontController::canonicalRedirection()
     *
     * @param string $canonical_url
     */
    protected function canonicalRedirection($canonical_url = '')
    {
        return;
    }

    /**
     * @param Exception $exception
     */
    private function handleException(Exception $exception)
    {
        $this->handleExceptionSendingToSentry($exception);

        $this->module->getLogger()->log(
            PsCheckoutException::PRESTASHOP_ORDER_NOT_FOUND === $exception->getCode() ? Logger::NOTICE : Logger::ERROR,
            'Webhook exception ' . $exception->getCode(),
            [
                'merchantId' => $this->merchantId,
                'shopId' => $this->shopId,
                'firebaseId' => $this->firebaseId,
                'payload' => $this->payload,
                'exception' => $exception,
                'trace' => $exception->getTraceAsString(),
            ]
        );

        http_response_code($this->getHttpCodeFromExceptionCode($exception->getCode()));
        header('Content-Type: application/json');

        $bodyReturn = json_encode($exception->getMessage());

        echo $bodyReturn;
    }

    /**
     * @param int $exceptionCode
     *
     * @return int
     */
    private function getHttpCodeFromExceptionCode($exceptionCode)
    {
        $httpCode = 500;

        switch ($exceptionCode) {
            case PsCheckoutException::PRESTASHOP_REFUND_ALREADY_SAVED:
                $httpCode = 200;
                break;
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_PSL_SIGNATURE_INVALID:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_SHOP_ID_INVALID:
                $httpCode = 401;
                break;
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_AMOUNT_INVALID:
                $httpCode = 406;
                break;
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_HEADER_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_SHOP_ID_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_MERCHANT_ID_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_PSX_ID_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_BODY_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_EVENT_TYPE_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_CATEGORY_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_RESOURCE_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_AMOUNT_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_CURRENCY_EMPTY:
            case PsCheckoutException::PSCHECKOUT_WEBHOOK_ORDER_ID_EMPTY:
            case PsCheckoutException::PSCHECKOUT_MERCHANT_IDENTIFIER_MISSING:
            case PsCheckoutException::PRESTASHOP_ORDER_NOT_FOUND:
                $httpCode = 422;
                break;
        }

        return $httpCode;
    }
}
