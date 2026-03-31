<?php
/**
 * Copyright © Fintoc. All rights reserved.
 */

namespace Fintoc\Payment\Controller\Checkout;

use Exception;
use Fintoc\Payment\Api\Data\TransactionInterface;
use Fintoc\Payment\Api\TransactionRepositoryInterface;
use Fintoc\Payment\Api\TransactionServiceInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderFactory;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Fintoc\Payment\Api\LoggerServiceInterface as LoggerInterface;

/**
 * Controller for handling Fintoc payment callbacks (success/cancel)
 */
class Commit extends Action
{
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var EncryptorInterface
     */
    protected $encryptor;

    /**
     * @var TransactionServiceInterface
     */
    protected $transactionService;

    /**
     * @var TransactionRepositoryInterface
     */
    protected $transactionRepository;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var CheckoutSession
     */
    protected $checkoutSession;

    /**
     * @var CookieManagerInterface
     */
    protected $cookieManager;

    /**
     * @var CookieMetadataFactory
     */
    protected $cookieMetadataFactory;

    /**
     * @param Context $context
     * @param RedirectFactory $resultRedirectFactory
     * @param ManagerInterface $messageManager
     * @param OrderFactory $orderFactory
     * @param EncryptorInterface $encryptor
     * @param TransactionServiceInterface $transactionService
     * @param TransactionRepositoryInterface $transactionRepository
     * @param LoggerInterface $logger
     * @param CheckoutSession $checkoutSession
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     */
    public function __construct(
        Context                        $context,
        RedirectFactory                $resultRedirectFactory,
        ManagerInterface               $messageManager,
        OrderFactory                   $orderFactory,
        EncryptorInterface             $encryptor,
        TransactionServiceInterface    $transactionService,
        TransactionRepositoryInterface $transactionRepository,
        LoggerInterface                $logger,
        CheckoutSession                $checkoutSession,
        CookieManagerInterface         $cookieManager,
        CookieMetadataFactory          $cookieMetadataFactory
    )
    {
        parent::__construct($context);
        $this->resultRedirectFactory = $resultRedirectFactory;
        $this->messageManager = $messageManager;
        $this->orderFactory = $orderFactory;
        $this->encryptor = $encryptor;
        $this->transactionService = $transactionService;
        $this->transactionRepository = $transactionRepository;
        $this->logger = $logger;
        $this->checkoutSession = $checkoutSession;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
    }

    /**
     * Execute action based on a request and return result
     *
     * @return ResultInterface
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            // Get action parameter (success or cancel)
            $action = $this->getRequest()->getParam('action');

            // Get encrypted transaction ID
            $encryptedTransactionId = $this->getRequest()->getParam('tr');

            if (!$encryptedTransactionId) {
                throw new LocalizedException(__('Missing transaction ID parameter'));
            }

            // Decrypt the transaction ID
            try {
                $transactionId = $encryptedTransactionId;
            } catch (Exception $e) {
                $this->logger->error('Commit: Error decrypting transaction ID: ' . $e->getMessage(), ['exception' => $e]);
                throw new LocalizedException(__('Invalid transaction ID'));
            }

            // Get the transaction from the repository
            try {
                $transaction = $this->transactionRepository->getByTransactionId($transactionId);
            } catch (Exception $e) {
                $this->logger->error('Commit: Error retrieving transaction: ' . $e->getMessage(), ['exception' => $e]);
                throw new LocalizedException(__('Transaction not found'));
            }

            // Get the order
            $orderId = $transaction->getOrderId();
            $order = $this->orderFactory->create()->load($orderId);

            if (!$order->getId()) {
                throw new LocalizedException(__('Order not found'));
            }

            // Process based on action
            if ($action === 'success') {
                return $this->processSuccessAction($transaction, $order);
            } elseif ($action === 'cancel') {
                return $this->processCancelAction($transaction, $order);
            } else {
                throw new LocalizedException(__('Invalid action parameter'));
            }

        } catch (Exception $e) {
            $this->logger->error('Commit: Error processing Fintoc callback: ' . $e->getMessage(), ['exception' => $e]);
            $this->messageManager->addErrorMessage($e->getMessage());
            try {
                $this->checkoutSession->restoreQuote();
            } catch (Exception $e2) {
                $this->logger->debug('Commit: Restore quote failed: ' . $e2->getMessage());
            }
            return $resultRedirect->setPath('checkout/cart');
        }
    }

    /**
     * Process success action
     *
     * @param TransactionInterface $transaction
     * @param Order $order
     * @return ResultInterface
     * @throws LocalizedException
     */
    private function processSuccessAction(TransactionInterface $transaction, Order $order)
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        // Update transaction status
        $this->transactionService->updateTransactionStatus(
            $transaction,
            TransactionInterface::STATUS_SUCCESS,
            [
                'updated_by' => 'commit_controller',
                'error_message' => null
            ]
        );

        // Add comment to order history
        $order->addCommentToStatusHistory(
            __(
                'Fintoc payment successful. Transaction ID: %1, Amount: %2 %3',
                $transaction->getTransactionId(),
                $transaction->getAmount(),
                $transaction->getCurrency()
            )
        );

        // Add additional payment information
        $payment = $order->getPayment();
        $payment->setAdditionalInformation('fintoc_transaction_status', TransactionInterface::STATUS_SUCCESS);
        $payment->setAdditionalInformation('fintoc_transaction_completed_at', date('Y-m-d H:i:s'));

        // Store transaction response data if available
        $responseData = $transaction->getResponseData();
        if ($responseData) {
            try {
                $responseArray = json_decode($responseData, true);
                if (is_array($responseArray)) {
                    foreach ($responseArray as $key => $value) {
                        if (is_scalar($value)) {
                            $payment->setAdditionalInformation('fintoc_response_' . $key, $value);
                        }
                    }
                }
            } catch (Exception $e) {
                $this->logger->error('Commit: Error processing response data: ' . $e->getMessage());
            }
        }

        $payment->save();
        $order->save();

        // Log the success
        $this->logger->info(
            'Commit: Fintoc payment successful',
            [
                'transaction_id' => $transaction->getTransactionId(),
                'order_id' => $order->getIncrementId()
            ]
        );

        // Deactivate the quote and clear it from the session so the cart
        // shows empty after successful payment. The quote was kept alive
        // during the redirect (restored in Create controller).
        try {
            $quote = $this->checkoutSession->getQuote();
            if ($quote && $quote->getId()) {
                $quote->setIsActive(false);
                $quote->save();
            }
            $this->checkoutSession->clearQuote();
            $this->checkoutSession->setQuoteId(null);
        } catch (Exception $e) {
            $this->logger->debug('Commit: deactivate quote failed: ' . $e->getMessage());
        }

        // Set order in session so the success page can display it
        $this->checkoutSession->setLastSuccessQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastQuoteId($order->getQuoteId());
        $this->checkoutSession->setLastOrderId($order->getId());
        $this->checkoutSession->setLastRealOrderId($order->getIncrementId());

        // Add a success message
        $this->messageManager->addSuccessMessage(__('Your payment was successful.'));

        // Redirect to success page
        return $resultRedirect->setPath('checkout/onepage/success');
    }

    /**
     * Bump the private_content_version cookie so the frontend JS
     * reloads customer-data sections (including the mini-cart).
     */
    private function bumpPrivateContentVersion(): void
    {
        $metadata = $this->cookieMetadataFactory->createPublicCookieMetadata()
            ->setDuration(315360000)
            ->setPath('/')
            ->setHttpOnly(false)
            ->setSameSite('Lax');

        $this->cookieManager->setPublicCookie(
            'private_content_version',
            md5((string) rand() . time()),
            $metadata
        );
    }

    /**
     * Process cancel action
     *
     * @param TransactionInterface $transaction
     * @param Order $order
     * @return ResultInterface
     * @throws LocalizedException
     */
    private function processCancelAction(TransactionInterface $transaction, Order $order)
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        // Update transaction status
        $this->transactionService->updateTransactionStatus(
            $transaction,
            TransactionInterface::STATUS_CANCELED,
            [
                'updated_by' => 'commit_controller',
                'error_message' => 'Payment canceled by customer'
            ]
        );

        // Cancel the order if it's not already canceled
        if ($order->getState() !== Order::STATE_CANCELED) {
            $order->cancel();
            $order->addCommentToStatusHistory(
                __(
                    'Fintoc payment canceled by customer. Transaction ID: %1, Amount: %2 %3',
                    $transaction->getTransactionId(),
                    $transaction->getAmount(),
                    $transaction->getCurrency()
                )
            );

            // Add additional payment information
            $payment = $order->getPayment();
            $payment->setAdditionalInformation('fintoc_transaction_status', TransactionInterface::STATUS_CANCELED);
            $payment->setAdditionalInformation('fintoc_transaction_canceled_at', date('Y-m-d H:i:s'));
            $payment->setAdditionalInformation('fintoc_cancel_reason', 'Payment canceled by customer');
            $payment->save();

            $order->save();
        }

        // Log the cancellation
        $this->logger->info(
            'Commit: Fintoc payment canceled',
            [
                'transaction_id' => $transaction->getTransactionId(),
                'order_id' => $order->getIncrementId(),
            ]
        );
        $this->logger->debug(
            'Commit: Fintoc payment canceled',
            [
                'transaction_id' => $transaction->getTransactionId(),
                'order_id' => $order->getIncrementId(),
                'request' => $this->getRequest()->getParams(),
            ]
        );

        // Add a message
        $this->messageManager->addErrorMessage(__('Your payment was canceled.'));

        // Restore quote to allow customer to retry checkout
        try {
            $this->checkoutSession->restoreQuote();
        } catch (Exception $e) {
            $this->logger->debug('Commit: Restore quote failed on cancel: ' . $e->getMessage());
        }

        // Redirect to failure page
        return $resultRedirect->setPath('checkout/cart');
    }
}
