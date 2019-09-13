<?php


namespace Okay\Modules\OkayCMS\GoogleMerchant\Init;


use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Entities\BrandsEntity;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\ProductsEntity;

class Init extends AbstractInit
{
    const TO_FEED_FIELD     = 'to__okaycms__google_merchant';
    const NOT_TO_FEED_FIELD = 'not_to__okaycms__google_merchant';
    const FEED_UPLOAD_FIELD = 'upload__okaycms__google_merchant';

    public function install()
    {
        $this->setModuleType(MODULE_TYPE_XML);
        $this->setBackendMainController('GoogleMerchantAdmin');
    }
    
    public function init()
    {
        $field = new EntityField(CategoriesEntity::class, self::TO_FEED_FIELD);
        $field->setTypeTinyInt(1);
        $this->registerEntityField($field);
        
        $field = new EntityField(BrandsEntity::class, self::TO_FEED_FIELD);
        $field->setTypeTinyInt(1);
        $this->registerEntityField($field);
        
        $field = new EntityField(ProductsEntity::class, self::TO_FEED_FIELD);
        $field->setTypeTinyInt(1);
        $this->registerEntityField($field);
        
        $field = new EntityField(ProductsEntity::class, self::NOT_TO_FEED_FIELD);
        $field->setTypeTinyInt(1);
        $this->registerEntityField($field);
        
        $this->registerBackendController('GoogleMerchantAdmin');
        $this->addBackendControllerPermission('GoogleMerchantAdmin', self::FEED_UPLOAD_FIELD);
        
        $this->registerEntityFilter(
            ProductsEntity::class,
            'okaycms__google_merchant__only',
            \Okay\Modules\OkayCMS\GoogleMerchant\ExtendsEntities\ProductsEntity::class,
            'okaycms__google_merchant__only'
        );
        
    }
    
}