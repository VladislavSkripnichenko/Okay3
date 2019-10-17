<?php


namespace Okay\Controllers;


use Okay\Entities\BrandsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\CategoriesEntity;
use Okay\Helpers\CatalogHelper;
use Okay\Helpers\FilterHelper;
use Okay\Helpers\ProductsHelper;

class BrandController extends AbstractController
{

    private $catalogType = 'brand';
    private $isFilterPage = false;

    /*Отображение страницы бренда*/
    public function render(
        BrandsEntity $brandsEntity,
        CategoriesEntity $categoriesEntity,
        CatalogHelper $catalogHelper,
        ProductsHelper $productsHelper,
        ProductsEntity $productsEntity,
        FilterHelper $filterHelper,
        $url,
        $filtersUrl = ''
    ) {
        
        $filterHelper->setFiltersUrl($filtersUrl);

        $sortProducts = null;
        $filter['visible'] = 1;

        $brand = $brandsEntity->get((string)$url);
        if (empty($brand) || (!$brand->visible && empty($_SESSION['admin']))) {
            return false;
        }

        // Если нашли фильтр по бренду, кидаем 404
        if (($currentBrandsIds = $filterHelper->getCurrentBrands($filtersUrl)) === false || !empty($currentBrandsIds)) {
            return false;
        }

        // Если нашли фильтр по свойствам, кидаем 404
        if (($currentFeatures = $filterHelper->getCurrentCategoryFeatures($filtersUrl)) === false || !empty($currentFeatures)) {
            return false;
        }
        
        if (($currentOtherFilters = $filterHelper->getCurrentOtherFilters($filtersUrl)) === false) {
            return false;
        }

        if (($currentPage = $filterHelper->getCurrentPage($filtersUrl)) === false) {
            return false;
        }
        
        if (($currentSort = $filterHelper->getCurrentSort($filtersUrl)) === false) {
            return false;
        }

        if (!empty($currentOtherFilters)) {
            $filter['other_filter'] = $currentOtherFilters;
            $this->design->assign('selected_other_filters', $currentOtherFilters);
        }

        // Сортировка товаров, сохраняем в сесси, чтобы текущая сортировка оставалась для всего сайта
        if (!empty($currentSort)) {
            $_SESSION['sort'] = $currentSort;
        }
        if (!empty($_SESSION['sort'])) {
            $sortProducts = $_SESSION['sort'];
        } else {
            $sortProducts = 'position';
        }
        $this->design->assign('sort', $currentSort);
        
        $filter['price'] = $catalogHelper->getPriceFilter($this->catalogType, $brand->id);
        
        $brand->categories = $categoriesEntity->find(['brand_id'=>$brand->id, 'category_visible'=>1]);
        $this->design->assign('brand', $brand);
        $filter['brand_id'] = $brand->id;
        
        $this->design->assign('other_filters', $catalogHelper->getOtherFilters($filter));

        if ((!empty($filter['price']) && $filter['price']['min'] !== '' && $filter['price']['max'] !== '' && $filter['price']['min'] !== null) || !empty($filter['other_filter'])) {
            $this->isFilterPage = true;
            $this->design->assign('is_filter_page', $this->isFilterPage);
        }
        
        $prices = $catalogHelper->getPrices($filter, $this->catalogType, $brand->id);
        $this->design->assign('prices', $prices);

        $paginate = $catalogHelper->paginate(
            $this->settings->get('products_num'),
            $currentPage,
            $filter,
            $this->design
        );
        $this->design->assign('current_page', $currentPage);
        
        if (!$paginate) {
            return false;
        }

        // Товары
        $products = $productsHelper->getProductList($filter, $sortProducts);
        $this->design->assign('products', $products);

        if ($this->request->get('ajax','boolean')) {
            $this->design->assign('ajax', 1);
            $result = new \stdClass;
            $result->products_content = $this->design->fetch('products_content.tpl');
            $result->products_pagination = $this->design->fetch('chpu_pagination.tpl');
            $result->products_sort = $this->design->fetch('products_sort.tpl');
            $result->features = $this->design->fetch('features.tpl');
            $result->selected_features = $this->design->fetch('selected_features.tpl');
            $this->response->setContent(json_encode($result), RESPONSE_JSON);
            return;
        }

        //lastModify
        $lastModify = $productsEntity->cols(['last_modify'])
            ->order('last_modify_desc')
            ->find([
                'brand_id' => $filter['brand_id'],
                'limit' => 1,
            ]);
        $lastModify[] = $brand->last_modify;
        if ($this->page) {
            $lastModify[] = $this->page->last_modify;
        }
        $this->response->setHeaderLastModify(max($lastModify));
        //lastModify END

        if ($this->isFilterPage === true) {
            $this->design->assign('set_canonical', 1);
        }
        
        // Устанавливаем мета-теги в зависимости от запроса
        if ($this->page) {
            $this->design->assign('meta_title', $this->page->meta_title);
            $this->design->assign('meta_keywords', $this->page->meta_keywords);
            $this->design->assign('meta_description', $this->page->meta_description);
        } elseif (isset($brand)) {
            $this->design->assign('meta_title', $brand->meta_title);
            $this->design->assign('meta_keywords', $brand->meta_keywords);
            $this->design->assign('meta_description', $brand->meta_description);
        }

        $relPrevNext = $this->design->fetch('products_rel_prev_next.tpl');
        $this->design->assign('rel_prev_next', $relPrevNext);
        $this->design->assign('sort_canonical', $filterHelper->getSortCanonical());
        
        $this->response->setContent('products.tpl');
    }

}
