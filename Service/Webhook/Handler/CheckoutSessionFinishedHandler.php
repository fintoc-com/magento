<?php
declare(strict_types=1);

namespace Fintoc\Payment\Service\Webhook\Handler;

use Fintoc\Payment\Service\Webhook\WebhookConstants;
use Fintoc\Payment\Service\Webhook\WebhookEvent;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;

class CheckoutSessionFinishedHandler extends AbstractWebhookHandler
{
    /**
     * Handles the webhook event for a checkout session completion.
     *
     * @param WebhookEvent $event The webhook event containing details about the session.
     * @return void
     * @throws LocalizedException If the order ID cannot be extracted from the session.
     */
    public function handle(WebhookEvent $event): void
    {
        $session = $event->getObject();
        $orderId = $this->extractOrderIncrementId($session);
        if (!$orderId) {
            throw new LocalizedException(__('No order ID found in checkout session metadata'));
        }

        $tx = $this->getFirstTransactionByOrder($orderId);
        if ($tx) {
            $this->appendWebhookPayload($tx, $event, WebhookConstants::EV_CS_FINISHED);
        }

        $pi = $session['payment_resource']['payment_intent'] ?? null;
        if (is_array($pi) && ($pi['next_action']['type'] ?? null) === 'cash_payment') {
            $this->holdForCashPayment($this->loadOrderOrFail($orderId));
        }

        $this->logger->debug('Checkout session finished traced', ['order' => $orderId]);
    }

    /**
     * Keep the order and its stock while the consumer pays the cash voucher at the store.
     *
     * @param Order $order
     * @return void
     */
    private function holdForCashPayment(Order $order): void
    {
        if ($order->canHold()) {
            $order->hold();
            $order->addCommentToStatusHistory(__('Fintoc cash payment pending. The order is on hold until the consumer pays at the store.'));
            $order->save();
        }
    }
}
