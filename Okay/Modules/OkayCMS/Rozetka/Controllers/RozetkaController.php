<?php


namespace Okay\Modules\OkayCMS\Rozetka\Controllers;


use Okay\Controllers\AbstractController;
use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Entities\BrandsEntity;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\ProductsEntity;
use Okay\Helpers\ProductsHelper;

class RozetkaController extends AbstractController
{
    
    public function render(
        ProductsEntity $productsEntity,
        ProductsHelper $productsHelper,
        CategoriesEntity $categoriesEntity,
        BrandsEntity $brandsEntity,
        Database $db,
        QueryFactory $queryFactory
    ) {

        if (!empty($this->currencies)) {
            $this->design->assign('main_currency', reset($this->currencies));
        }

        $sql = $queryFactory->newSqlQuery();
        $sql->setStatement('SET SQL_BIG_SELECTS=1');
        $db->query($sql);
        
        $sql = $queryFactory->newSqlQuery();
        $sql->setStatement("SELECT id, parent_id, to_rozetka FROM __categories WHERE to_rozetka=1");
        $db->query($sql);
        $categoriesToRozetka = $db->results();
        $uploadCategories = [];

        $uploadCategories = $this->addAllChildrenToList($categoriesEntity, $categoriesToRozetka, $uploadCategories);
        
        $filter['visible'] = 1;
        $filter['rozetka_only'] = $uploadCategories;
        
        if ($this->settings->get('upload_only_available_to_rozetka')) {
            $filter['in_stock'] = 1;
        }

        $productsIds = $productsEntity->cols(['id'])->find($filter);

        if (!empty($productsIds)) {
            $allBrands = $brandsEntity->mappedBy('id')->find(['product_id' => $productsIds]);
            $this->design->assign('all_brands', $allBrands);
        }

        $this->design->assign('all_categories', $categoriesEntity->find());

        $this->response->setContentType(RESPONSE_XML);

        $this->response->sendHeaders();
        $this->response->sendStream(pack('CCC', 0xef, 0xbb, 0xbf));
        $this->response->sendStream($this->design->fetch('feed_head.xml.tpl'));

        // Выдаём товары пачками
        $itemsPerPage = $this->settings->get('okaycms__rozetka_xml__products_per_page');
        $itemsPerPage = !empty($itemsPerPage) ? $itemsPerPage : 1000;
        $productsCount = $productsEntity->count($filter);

        $pages = ceil($productsCount/$itemsPerPage);
        $pages = max(1, $pages);
        
        $variantsFilter = $filter;
        
        // Проходимся пагинацией, выводим товары пачками
        for ($page = 1; $page <= $pages; $page++) {
            $filter['limit'] = $itemsPerPage;
            $filter['page'] = $page;

            $products = $productsEntity->cols([
                'description',
                'name',
                'id',
                'brand_id',
                'url',
                'annotation',
                'main_category_id',
            ])
                ->mappedBy('id')
                ->find($filter);
            
            if (!empty($products)) {
                $products = $productsHelper->attachVariants($products, $variantsFilter);
                $products = $productsHelper->attachImages($products);
                $products = $productsHelper->attachFeatures($products, ['feed' => 1]);
            }
            
            $this->design->assign('products', $products);
            $this->response->sendStream($this->design->fetch('feed_offers.xml.tpl'));
        }

        $this->response->sendStream($this->design->fetch('feed_footer.xml.tpl'));
    }
    
    private function addAllChildrenToList(CategoriesEntity $categoriesEntity, $categories, $uploadCategories)
    {
        foreach ($categories as $c) {
            $category = $categoriesEntity->get((int)$c->id);
            $uploadCategories[] = $category->id;
            if (!empty($category->subcategories)) {
                $uploadCategories = $this->addAllChildrenToList($categoriesEntity, $category->subcategories, $uploadCategories);
            }
        }
        return $uploadCategories;
    }
    
}
