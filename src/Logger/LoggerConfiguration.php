<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\PrestashopCheckout\Logger;

use Monolog\Logger;
use PrestaShop\Module\PrestashopCheckout\Configuration\PrestaShopConfiguration;

class LoggerConfiguration
{
    const MAX_FILES = 15;

    /**
     * @var PrestaShopConfiguration
     */
    private $configuration;

    /**
     * @param PrestaShopConfiguration $configuration
     */
    public function __construct(PrestaShopConfiguration $configuration)
    {
        $this->configuration = $configuration;
    }

    /**
     * @return int
     */
    public function getMaxFiles()
    {
        return (int) $this->configuration->get(
            'PS_CHECKOUT_LOGGER_MAX_FILES',
            [
                'default' => static::MAX_FILES,
                'global' => true,
            ]
        );
    }

    /**
     * @return int
     */
    public function getLevel()
    {
        return (int) $this->configuration->get(
            'PS_CHECKOUT_LOGGER_LEVEL',
            [
                'default' => Logger::ERROR,
                'global' => true,
            ]
        );
    }
}
