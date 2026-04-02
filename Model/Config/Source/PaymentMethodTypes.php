<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */
declare(strict_types=1);

namespace Fintoc\Payment\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for available Fintoc payment method types.
 */
class PaymentMethodTypes implements OptionSourceInterface
{
    /**
     * @inheritDoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'bank_transfer', 'label' => __('Bank Transfer')],
            ['value' => 'card', 'label' => __('Card (Visa / Mastercard)')],
            ['value' => 'installments_payment', 'label' => __('Buy Now Pay Later')],
        ];
    }
}
