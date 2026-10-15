<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Model\Ui;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Model\Payment;
use Fintoc\Payment\Service\Checkout\KnownPaymentMethods;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Configuration provider for Fintoc payment method
 */
class ConfigProvider implements ConfigProviderInterface
{
    /**
     * Titles that the checkout replaces with the organization's payment methods.
     */
    private const DEFAULT_TITLES = ['Fintoc', 'Paga con tu Banco'];

    /**
     * @var ConfigurationServiceInterface
     */
    protected $configService;

    /**
     * @var KnownPaymentMethods
     */
    private $knownPaymentMethods;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @param ConfigurationServiceInterface $configService
     * @param KnownPaymentMethods $knownPaymentMethods
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        ConfigurationServiceInterface $configService,
        KnownPaymentMethods $knownPaymentMethods,
        StoreManagerInterface $storeManager
    ) {
        $this->configService = $configService;
        $this->knownPaymentMethods = $knownPaymentMethods;
        $this->storeManager = $storeManager;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return [
            'payment' => [
                Payment::CODE => [
                    'title' => $this->resolveTitle(),
                    'subtitle' => (string)__('With Fintoc'),
                    'isDebugMode' => $this->configService->isDebugMode()
                ]
            ]
        ];
    }

    /**
     * Title shown at checkout: the merchant's own title, or the organization's payment methods.
     *
     * @return string
     */
    private function resolveTitle(): string
    {
        $title = trim($this->configService->getTitle());
        if ($title !== '' && !in_array($title, self::DEFAULT_TITLES, true)) {
            return $title;
        }

        $mexican = $this->storeManager->getStore()->getBaseCurrencyCode() === 'MXN';
        $labels = [
            'bank_transfer' => $mexican ? __('SPEI') : __('your bank'),
            'card' => __('card'),
            'cash' => $mexican ? __('cash') : null,
        ];

        $types = $this->knownPaymentMethods->getTypes();
        $names = [];
        foreach ($labels as $type => $label) {
            if ($label && in_array($type, $types, true)) {
                $names[] = (string)$label;
            }
        }

        if (!$names) {
            return (string)__('Fintoc');
        }

        if ($types === ['card']) {
            return (string)__('Credit or debit card');
        }

        if ($types === ['cash']) {
            return (string)__('Pay in cash');
        }

        $last = array_pop($names);
        $list = $names ? (string)__('%1 or %2', implode(', ', $names), $last) : $last;

        return (string)__('Pay with %1', $list);
    }
}
