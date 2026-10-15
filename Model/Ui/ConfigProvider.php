<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Model\Ui;

use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Model\Payment;
use Fintoc\Payment\Service\Checkout\KnownPaymentMethods;
use Magento\Checkout\Model\ConfigProviderInterface;
use Magento\Framework\View\Asset\Repository as AssetRepository;
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
     * Official Fintoc images for checkouts that only offer bank transfer, per country.
     */
    private const BANK_TRANSFER_COMBO_URLS = [
        'cl' => 'https://assets.fintoc.com/?img_name=combo_cl_light',
        'mx' => 'https://assets.fintoc.com/?img_name=combo_mx_dark',
    ];

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
     * @var AssetRepository
     */
    private $assetRepository;

    /**
     * @param ConfigurationServiceInterface $configService
     * @param KnownPaymentMethods $knownPaymentMethods
     * @param StoreManagerInterface $storeManager
     * @param AssetRepository $assetRepository
     */
    public function __construct(
        ConfigurationServiceInterface $configService,
        KnownPaymentMethods $knownPaymentMethods,
        StoreManagerInterface $storeManager,
        AssetRepository $assetRepository
    ) {
        $this->configService = $configService;
        $this->knownPaymentMethods = $knownPaymentMethods;
        $this->storeManager = $storeManager;
        $this->assetRepository = $assetRepository;
    }

    /**
     * Retrieve assoc array of checkout configuration
     *
     * @return array
     */
    public function getConfig()
    {
        $logoRows = $this->resolveLogoRows();
        $twoRows = count($logoRows) > 1;
        if (!$twoRows) {
            $logoRows[0][] = $this->getImageUrl('fintoc-chip.svg');
        }

        return [
            'payment' => [
                Payment::CODE => [
                    'title' => $this->resolveTitle(),
                    'subtitle' => (string)__('With Fintoc'),
                    'comboLogo' => $this->resolveComboLogo(),
                    'logoRows' => $logoRows,
                    'brandLogo' => $twoRows ? $this->getImageUrl('fintoc-vertical.svg') : null,
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

        $mexican = $this->isMexicanStore();
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

    /**
     * Official bank transfer image, when bank transfer is the only payment method.
     *
     * @return string|null
     */
    private function resolveComboLogo(): ?string
    {
        if ($this->getLogoTypes() !== ['bank_transfer']) {
            return null;
        }

        return self::BANK_TRANSFER_COMBO_URLS[$this->isMexicanStore() ? 'mx' : 'cl'];
    }

    /**
     * Logos of the organization's payment methods, in two rows: bank transfer and cash, then card.
     *
     * @return string[][]
     */
    private function resolveLogoRows(): array
    {
        $types = $this->getLogoTypes();
        $country = $this->isMexicanStore() ? 'mx' : 'cl';
        $bankTransfer = $country === 'mx'
            ? ['spei.svg']
            : ['bank-cl-1.svg', 'bank-cl-2.svg', 'bank-cl-3.svg', 'bank-cl-4.svg', 'bank-cl-more.svg'];
        $rows = [
            [
                'bank_transfer' => $bankTransfer,
                'cash' => $country === 'mx' ? ['cash-mx-1.svg', 'cash-mx-2.svg', 'cash-mx-3.svg', 'cash-mx-more.svg'] : [],
            ],
            [
                'card' => ['card-apple-pay.svg', 'card-mastercard.svg', 'card-visa.svg'],
            ],
        ];

        $logoRows = [];
        foreach ($rows as $row) {
            $urls = [];
            foreach ($row as $type => $files) {
                if (in_array($type, $types, true)) {
                    foreach ($files as $file) {
                        $urls[] = $this->getImageUrl($file);
                    }
                }
            }
            if ($urls) {
                $logoRows[] = $urls;
            }
        }

        return $logoRows;
    }

    /**
     * Payment method types to show logos for; bank transfer until the first checkout session.
     *
     * @return string[]
     */
    private function getLogoTypes(): array
    {
        return $this->knownPaymentMethods->getTypes() ?: ['bank_transfer'];
    }

    /**
     * Whether the store sells in Mexican pesos.
     *
     * @return bool
     */
    private function isMexicanStore(): bool
    {
        return $this->storeManager->getStore()->getBaseCurrencyCode() === 'MXN';
    }

    /**
     * Public URL of an image of this module.
     *
     * @param string $file
     * @return string
     */
    private function getImageUrl(string $file): string
    {
        return $this->assetRepository->getUrl('Fintoc_Payment::images/' . $file);
    }
}
