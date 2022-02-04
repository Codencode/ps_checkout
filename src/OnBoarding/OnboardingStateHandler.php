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

namespace PrestaShop\Module\PrestashopCheckout\OnBoarding;

use PrestaShop\Module\PrestashopCheckout\Api\Psl\Onboarding;
use PrestaShop\Module\PrestashopCheckout\Configuration\PrestashopCheckoutConfiguration;
use PrestaShop\Module\PrestashopCheckout\Dispatcher\ShopDispatcher;
use PrestaShop\Module\PrestashopCheckout\PsxData\PsxDataPrepare;
use PrestaShop\Module\PrestashopCheckout\PsxData\PsxDataValidation;
use PrestaShop\Module\PrestashopCheckout\Session\Onboarding\OnboardingSessionManager;

class OnboardingStateHandler
{
    /**
     * @var \PrestaShop\Module\PrestashopCheckout\Session\Onboarding\OnboardingSessionManager
     */
    private $onboardingSessionManager;

    /**
     * @var \PrestaShop\Module\PrestashopCheckout\OnBoarding\OnboardingState
     */
    private $onboardingState;

    /**
     * @var \PrestaShop\Module\PrestashopCheckout\Configuration\PrestashopCheckoutConfiguration
     */
    private $psCheckoutConfiguration;

    /**
     * @var \PrestaShop\Module\PrestashopCheckout\Api\Psl\Onboarding
     */
    private $onboardingApi;

    /**
     * @var \PrestaShop\Module\PrestashopCheckout\Session\Session|null
     */
    private $onboardingSession;

    /**
     * @param OnboardingSessionManager $onboardingSessionManager
     * @param OnboardingState $onboardingState
     * @param PrestashopCheckoutConfiguration $psCheckoutConfiguration
     * @param Onboarding $onboardingApi
     */
    public function __construct(
        OnboardingSessionManager $onboardingSessionManager,
        OnboardingState $onboardingState,
        PrestashopCheckoutConfiguration $psCheckoutConfiguration,
        Onboarding $onboardingApi
    ) {
        $this->onboardingSessionManager = $onboardingSessionManager;
        $this->onboardingState = $onboardingState;
        $this->psCheckoutConfiguration = $psCheckoutConfiguration;
        $this->onboardingApi = $onboardingApi;
    }

    /**
     * Handle onboarding session state
     *
     * @return array|null
     */
    public function handle()
    {
        $this->onboardingSession = $this->onboardingSessionManager->getOpened() ?:
            $this->onboardingSessionManager->getLatestOpenedSession();

        if (!$this->onboardingSession) {
            $this->handleFirebaseOnboarding();

            if (true === $this->handleShopDataCollect()) {
                $this->handlePaypalOnboarding();
            }
        }

        return $this->onboardingSession ? $this->onboardingSession->toArray() : null;
    }

    /**
     * Handle Firebase onboarding
     *
     * @return void
     */
    private function handleFirebaseOnboarding()
    {
        if ($this->onboardingState->isFirebaseOnboarded()) {
            $firebaseConfiguration = $this->psCheckoutConfiguration->getFirebase();
            $data = json_decode(json_encode([
                'account_id' => $firebaseConfiguration['accountId'],
                'account_email' => $firebaseConfiguration['email'],
            ]));

            $this->onboardingSession = $this->onboardingSessionManager->openOnboarding($data);
        }
    }

    /**
     * Handle shop data collect
     *
     * @return bool
     */
    private function handleShopDataCollect()
    {
        if ($this->onboardingState->isShopDataCollected()) {
            $shopDataConfiguration = $this->psCheckoutConfiguration->getShopData();
            $psxForm = json_decode($shopDataConfiguration['psxForm'], true);
            $data = [
                'form' => $psxForm,
            ];
            $data = array_merge(json_decode($this->onboardingSession->getData(), true), $data);

            $this->onboardingSession->setData(json_encode($data));
            $this->onboardingSession = $this->onboardingSessionManager->apply('collect_shop_data', $this->onboardingSession->toArray(true));

            // PsxForm validation
            $psxForm = (new PsxDataPrepare($psxForm))->prepareData();
            $errors = (new PsxDataValidation())->validateData($psxForm);

            // TODO : Remove this part after implement SSE + Full CQRS
            $createShop = $this->onboardingApi->createShop(array_filter($psxForm));

            if ($createShop['status']) {
                $onboard = $this->onboardingApi->onboard();

                if (isset($onboard['onboardingLink'])) {
                    (new ShopDispatcher())->dispatchEventType([
                        'resource' => [
                            'shop' => [
                                'paypal' => [
                                    'onboard' => [
                                        'links' => [
                                            1 => [
                                                'href' => $onboard['onboardingLink'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ]);
                }

                return $onboard['status'];
            }
        }

        return false;
    }

    /**
     * Handle PayPal onboarding
     *
     * @return void
     */
    private function handlePaypalOnboarding()
    {
        if ($this->onboardingState->isPaypalOnboarded()) {
            $merchantId = $this->psCheckoutConfiguration->getPaypal()['merchantId'];
            $onboardingSession = json_decode($this->onboardingSession->getData(), true);
            $data = [
                'shop' => [
                    'merchantId' => $merchantId,
                ],
            ];
            $data = array_merge(json_decode($this->onboardingSession->getData(), true), $data);

            $this->onboardingSession->setData(json_encode($data));
            $this->onboardingApi->forceUpdateMerchantIntegrations($merchantId);
        }
    }
}
