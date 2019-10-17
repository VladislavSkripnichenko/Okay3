<?php


namespace Okay\Modules\OkayCMS\NovaposhtaCost\Extenders;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Modules\Module;
use Okay\Core\Request;
use Okay\Entities\DeliveriesEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;

class BackendExtender implements ExtensionInterface
{
    
    private $request;
    private $entityFactory;
    private $design;
    private $module;
    
    public function __construct(Request $request, EntityFactory $entityFactory, Design $design, Module $module)
    {
        $this->request = $request;
        $this->entityFactory = $entityFactory;
        $this->design = $design;
        $this->module = $module;
    }
    
    /**
     * @param $variants
     * @return mixed
     * метод корректирует данные для поля volume, т.к. оно decimal, туда нельзя строку писать
     */
    public function correctVariantsVolume(array $variants)
    {
        foreach ($variants as $variant) {
            if (empty($variant->volume)) {
                $variant->volume = 0;
            }
        }
        
        return $variants;
    }
    
    public function getDeliveryDataProcedure($delivery, $order)
    {
        $moduleId = $this->module->getModuleIdByNamespace(__NAMESPACE__);
        $this->design->assign('novaposhta_module_id', $moduleId);
        
        if (!empty($order->id)) {
            /** @var NPCostDeliveryDataEntity $npDdEntity */
            $npDdEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);

            $npDeliveryData = $npDdEntity->getByOrderId($order->id);
            $this->design->assign('novaposhta_delivery_data', $npDeliveryData);
        }
    }
    
    public function updateDeliveryDataProcedure($order)
    {
        if (!empty($order->id)) {
            
            $moduleId = $this->module->getModuleIdByNamespace(__NAMESPACE__);
            
            /** @var NPCostDeliveryDataEntity $npDdEntity */
            $npDdEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
            $npDeliveryData = $npDdEntity->getByOrderId($order->id);
            
            if (!empty($order->delivery_id)) {
                /** @var DeliveriesEntity $deliveryEntity */
                $deliveryEntity = $this->entityFactory->get(DeliveriesEntity::class);
                $delivery = $deliveryEntity->get($order->delivery_id);
                
                if ($delivery->module_id == $moduleId) {
                    $npDeliveryData->city_id = $this->request->post('novaposhta_city_id');
                    $npDeliveryData->warehouse_id = $this->request->post('novaposhta_warehouse_id');
                    $npDeliveryData->delivery_term = $this->request->post('novaposhta_delivery_term');
                    $npDeliveryData->redelivery = $this->request->post('novaposhta_redelivery');
                    if (!empty($npDeliveryData->id)) {
                        $npDdEntity->update($npDeliveryData->id, $npDeliveryData);
                    } else {
                        $npDeliveryData->order_id = $order->id;
                        $npDdEntity->add($npDeliveryData);
                    }
                } elseif (!empty($npDeliveryData->id)) {
                    $npDdEntity->delete($npDeliveryData->id);
                }
            } elseif (!empty($npDeliveryData->id)) {
                $npDdEntity->delete($npDeliveryData->id);
            }
            
            
            $this->design->assign('novaposhta_delivery_data', $npDeliveryData);
        }
    }
    
}