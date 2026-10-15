<?php
declare(strict_types=1);

namespace Fintoc\Payment\Observer;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\LoggerServiceInterface as LoggerInterface;
use Fintoc\Payment\Model\Payment;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

class RestoreQuoteOfUnpaidOrder implements ObserverInterface
{
    /** @var CheckoutSession */
    private $checkoutSession;
    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CheckoutSession $checkoutSession,
        LoggerInterface $logger
    )
    {
        $this->checkoutSession = $checkoutSession;
        $this->logger = $logger;
    }

    public function execute(Observer $observer): void
    {
        $order = $this->checkoutSession->getLastRealOrder();
        $payment = $order->getPayment();
        if (!$order->getId() || !$payment || $payment->getMethod() !== Payment::CODE) {
            return;
        }

        if ($order->getState() !== Order::STATE_NEW
            || $payment->getAdditionalInformation('fintoc_transaction_status') === TransactionInterface::STATUS_SUCCESS
            || !$payment->getAdditionalInformation('fintoc_checkout_id')) {
            return;
        }

        try {
            $this->checkoutSession->restoreQuote();
            $this->logger->info('Restored quote of unpaid Fintoc order', ['order_id' => $order->getIncrementId()]);
        } catch (Exception $e) {
            $this->logger->error('Restore quote of unpaid Fintoc order failed: ' . $e->getMessage(), [
                'order_id' => $order->getIncrementId(),
            ]);
        }
    }
}
