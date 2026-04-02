<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Service\Checkout;

use Fintoc\Payment\Api\Checkout\MetadataBuilderInterface;
use Fintoc\Payment\Api\Checkout\RequestBuilderInterface;
use Fintoc\Payment\Api\ConfigurationServiceInterface;
use Fintoc\Payment\Utils\AmountUtils;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Default request payload builder for creating Fintoc checkout sessions.
 *
 * Third-parties can modify the payload by creating plugins for
 * \Fintoc\Payment\Api\Checkout\RequestBuilderInterface::build().
 */
class RequestBuilder implements RequestBuilderInterface
{
    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var MetadataBuilderInterface */
    private $metadataBuilder;

    /** @var ConfigurationServiceInterface */
    private $configService;

    /**
     * @param StoreManagerInterface $storeManager
     * @param MetadataBuilderInterface $metadataBuilder
     * @param ConfigurationServiceInterface $configService
     */
    public function __construct(
        StoreManagerInterface $storeManager,
        MetadataBuilderInterface $metadataBuilder,
        ConfigurationServiceInterface $configService
    ) {
        $this->storeManager = $storeManager;
        $this->metadataBuilder = $metadataBuilder;
        $this->configService = $configService;
    }

    /**
     * {@inheritdoc}
     */
    public function build(OrderInterface $order, string $transactionId): array
    {
        $baseUrl = $this->storeManager->getStore()->getBaseUrl();

        $payload = [
            'amount' => AmountUtils::roundToIntHalfUp((float) $order->getGrandTotal()),
            'currency' => $order->getOrderCurrencyCode(),
            'cancel_url' => $baseUrl . 'fintoc/checkout/commit/action/cancel/tr/' . rawurlencode($transactionId),
            'success_url' => $baseUrl . 'fintoc/checkout/commit/action/success/tr/' . rawurlencode($transactionId),
            'customer_email' => $order->getCustomerEmail(),
            'metadata' => $this->metadataBuilder->build($order, $transactionId),
            'payment_method_types' => $this->configService->getPaymentMethodTypes(),
        ];

        // Hook point: Plugins may add/edit/remove top-level payload keys here.
        return $payload;
    }
}
