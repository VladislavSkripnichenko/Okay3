<?php


namespace Okay\Helpers;


use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Module;
use Okay\Core\Request;
use Okay\Core\ServiceLocator;
use Okay\Entities\PaymentsEntity;
use Psr\Log\LoggerInterface;

class PaymentsHelper
{

    private $entityFactory;
    private $module;
    private $logger;

    public function __construct(EntityFactory $entityFactory, Module $module, LoggerInterface $logger)
    {
        $this->entityFactory = $entityFactory;
        $this->module = $module;
        $this->logger = $logger;
    }
    
    /**
     * @var $cart
     * @return array
     * @throws \Exception
     * 
     * Метод возвращает способы оплаты для корзины
     */
    public function getCartPaymentsList($cart)
    {
        /** @var PaymentsEntity $paymentsEntity */
        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);

        $payments = $paymentsEntity->mappedBy('id')->find(['enabled'=>1]);
        return ExtenderFacade::execute(__METHOD__, $payments, func_get_args());
    }

    /**
     * @param $paymentMethods
     * @param $activeDelivery
     * @return object
     * 
     * Метод возвращает активный способ оплаты, который должен быть отмечен как выбран
     */
    public function getActivePaymentMethod($paymentMethods, $activeDelivery)
    {
        $SL = new ServiceLocator();
        
        /** @var Request $request */
        $request = $SL->getService(Request::class);
        
        if (($paymentId = $request->post('payment_method_id', 'integer')) && isset($paymentMethods[$paymentId])) {
            $activePayment = $paymentMethods[$paymentId];
        } elseif (($firstDeliveryPaymentId = reset($activeDelivery->payment_methods_ids)) && isset($paymentMethods[$firstDeliveryPaymentId])) {
            $activePayment = $paymentMethods[$firstDeliveryPaymentId];
        } else {
            $activePayment = reset($paymentMethods);
        }

        return ExtenderFacade::execute(__METHOD__, $activePayment, func_get_args());
    }
}