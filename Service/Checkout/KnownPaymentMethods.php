<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Service\Checkout;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Magento\Framework\FlagManager;

/**
 * Payment method types of the last checkout session, per mode.
 *
 * The session response resolves which payment methods the Fintoc organization
 * has enabled; the checkout uses them to name the method and pick its logos.
 */
class KnownPaymentMethods
{
    private const FLAG_CODE = 'fintoc_known_payment_methods';

    /**
     * @var FlagManager
     */
    private $flagManager;

    /**
     * @var ConfigurationServiceInterface
     */
    private $configService;

    /**
     * @param FlagManager $flagManager
     * @param ConfigurationServiceInterface $configService
     */
    public function __construct(FlagManager $flagManager, ConfigurationServiceInterface $configService)
    {
        $this->flagManager = $flagManager;
        $this->configService = $configService;
    }

    /**
     * Store the payment method types returned by a checkout session.
     *
     * @param array $session
     * @return void
     */
    public function saveFromSession(array $session): void
    {
        if (empty($session['mode']) || !isset($session['payment_method_types']) || !is_array($session['payment_method_types'])) {
            return;
        }

        $known = $this->flagManager->getFlagData(self::FLAG_CODE) ?: [];
        $known[(string)$session['mode']] = array_values(array_map('strval', $session['payment_method_types']));
        $this->flagManager->saveFlag(self::FLAG_CODE, $known);
    }

    /**
     * Payment method types for the mode of the configured secret key.
     *
     * @return string[]
     */
    public function getTypes(): array
    {
        $known = $this->flagManager->getFlagData(self::FLAG_CODE) ?: [];
        $mode = strpos($this->configService->getApiSecret(), 'sk_live_') === 0 ? 'live' : 'test';

        return $known[$mode] ?? [];
    }
}
