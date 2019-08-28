<?php


namespace Okay\Controllers;


use Aura\SqlQuery\QueryFactory;
use Okay\Core\Database;
use Okay\Entities\CouponsEntity;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\OrderStatusEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Logic\OrdersLogic;

class OrderController extends AbstractController
{
    
    public function render(
        OrdersEntity $ordersEntity,
        CouponsEntity $couponsEntity,
        PaymentsEntity $paymentsEntity,
        DeliveriesEntity $deliveriesEntity,
        OrderStatusEntity $orderStatusEntity,
        CurrenciesEntity $currenciesEntity,
        OrdersLogic $ordersLogic,
        $url
    ) {
        $order = $ordersEntity->get((string)$url);

        if (empty($order)) {
            return false;
        }

        $purchases = $ordersLogic->getOrderPurchases(intval($order->id));
        if (!$purchases) {
            return false;
        }
        
        /*Выбор другого способа оплаты*/
        if ($this->request->method('post')) {
            if ($paymentMethodId = $this->request->post('payment_method_id', 'integer')) {
                $ordersEntity->update($order->id, ['payment_method_id'=>$paymentMethodId]);
                $order = $ordersEntity->get((int)$order->id);
            } elseif ($this->request->post('reset_payment_method')) {
                $ordersEntity->update($order->id, ['payment_method_id'=>null]);
                $order = $ordersEntity->get((int)$order->id);
            }
        }
        
        if (!empty($order->coupon_code)) {
            $order->coupon = $couponsEntity->get((string)$order->coupon_code);
            if ($order->coupon && $order->coupon->valid && $order->total_price >= $order->coupon->min_order_price) {
                if ($order->coupon->type == 'absolute') {
                    // Абсолютная скидка не более суммы заказа
                    $order->coupon->coupon_percent = round(100 - ($order->total_price * 100) / ($order->total_price + $order->coupon->value), 2);
                } else {
                    $order->coupon->coupon_percent = $order->coupon->value;
                }
            }
        }
        
        // Способ доставки
        $delivery = $deliveriesEntity->get((int)$order->delivery_id);
        $this->design->assign('delivery', $delivery);
        $orderStatus = $orderStatusEntity->find(["status"=>intval($order->status_id)]);
        $this->design->assign('order_status', reset($orderStatus));
        $this->design->assign('order', $order);
        $this->design->assign('purchases', $purchases);
        
        // Способ оплаты
        if (!empty($order->payment_method_id)) {
            $payment_method = $paymentsEntity->get((int)$order->payment_method_id);
            $this->design->assign('payment_method', $payment_method);
        }
        
        // Варианты оплаты
        $paymentMethods = $paymentsEntity->find([
            'delivery_id'=>$order->delivery_id,
            'enabled'=>1,
        ]);
        $this->design->assign('payment_methods', $paymentMethods);
        
        // Все валюты
        $this->design->assign('all_currencies', $currenciesEntity->find());
        
        // Выводим заказ
        $this->response->setContent($this->design->fetch('order.tpl'));
    }

    /*Скачивание цифрового товара*/
    public function download(
        Orders $ordersEntity,
        QueryFactory $queryFactory,
        Database $database,
        $url,
        $file
    ) {
        
        $order = $ordersEntity->get((string)$url);
        if (empty($order)) {
            return false;
        }
        
        if (!$order->paid) {
            return false;
        }

        // Проверяем, есть ли такой файл в покупках
        $select = $queryFactory->newSelect();
        $select->from('__purchases AS p')
            ->cols(['p.id'])
            ->join('LEFT', '__variants AS v', 'p.variant_id=v.id')
            ->where('p.order_id=:order_id')
            ->where('v.attachment=:attachment')
            ->limit(1)
            ->bindValues([
                'order_id' => $order->id,
                'attachment' => $file,
            ]);
        
        $database->query($select);
        
        if (!$database->result()) {
            return false;
        }
        
        $this->response->addHeader('Content-type: application/force-download');
        $this->response->addHeader('Content-Disposition: attachment; filename=\"$file\"');
        $this->response->addHeader('Content-Length: ".filesize($this->config->root_dir.$this->config->downloads_dir.$file)');
        $this->response->sendHeaders();
        
        readfile($this->config->root_dir.$this->config->downloads_dir.$file);
        
    }
    
}
