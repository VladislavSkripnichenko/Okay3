<?php


namespace Okay\Helpers;


use Okay\Core\Cart;
use Okay\Core\Comparison;
use Okay\Core\Config;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\FrontTranslations;
use Okay\Core\JsSocial;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Extender\QueueExtender;
use Okay\Core\Modules\Module;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Router;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Core\TemplateConfig;
use Okay\Core\WishList;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\LanguagesEntity;
use Okay\Entities\MenuEntity;
use Okay\Entities\MenuItemsEntity;
use Okay\Entities\PagesEntity;
use Okay\Entities\UserGroupsEntity;
use Okay\Entities\UsersEntity;

class MainHelper
{
    
    private $allLanguages;
    private $allCurrencies;
    private $currentLanguage;
    private $currentCurrency;
    private $currentUser;
    private $currentUserGroup;
    private $currentPage;
    private $SL;
    
    public function __construct()
    {
        $this->SL = new ServiceLocator();
        /** @var EntityFactory $entityFactory */
        $entityFactory = $this->SL->getService(EntityFactory::class);
        /** @var Request $request */
        $request = $this->SL->getService(Request::class);
        /** @var Response $response */
        $response = $this->SL->getService(Response::class);
        
        $languagesEntity = $entityFactory->get(LanguagesEntity::class);
        $langId = $request->getLangId();
        $this->currentLanguage = $languagesEntity->get($langId);
        $this->allLanguages = $languagesEntity->find();

        /** @var PagesEntity $pagesEntity */
        $pagesEntity = $entityFactory->get(PagesEntity::class);
        $this->currentPage = $pagesEntity->get($request->getPageUrl());

        /** @var CurrenciesEntity $currenciesEntity */
        $currenciesEntity = $entityFactory->get(CurrenciesEntity::class);
        // Все валюты
        $this->allCurrencies = $currenciesEntity->find(['enabled'=>1]);

        // Выбор текущей валюты
        if ($currencyId = $request->get('currency_id', 'integer')) {
            $_SESSION['currency_id'] = $currencyId;
            $response->redirectTo($request->url(['currency_id'=>null]));
        }
        // Берем валюту из сессии
        if (isset($_SESSION['currency_id'])) {
            $this->currentCurrency = $currenciesEntity->get((int)$_SESSION['currency_id']);
        } else {
            $this->currentCurrency = reset($this->allCurrencies);
            $_SESSION['currency_id'] = $this->currentCurrency->id;
        }

        // Пользователь, если залогинен
        if (isset($_SESSION['user_id'])) {
            /** @var UsersEntity $usersEntity */
            $usersEntity = $entityFactory->get(UsersEntity::class);

            /** @var UserGroupsEntity $userGroupsEntity */
            $userGroupsEntity = $entityFactory->get(UserGroupsEntity::class);

            $user = $usersEntity->get((int)$_SESSION['user_id']);
            if (!empty($user)) {
                $this->currentUser = $user;
                $this->currentUserGroup = $userGroupsEntity->get($this->currentUser->group_id);
            }
        }
    }

    /**
     * Метод, который можно расширять модулями. Выполняется он после работы контроллера
     * 
     * @throws \Exception
     */
    public function afterControllerProcedure()
    {
        QueueExtender::execute(__METHOD__, null, func_get_args());
    }
    
    /**
     * Метод передает в дизайн все переменные, которые могут там понадобиться
     * 
     * @throws \Exception
     */
    public function setDesignDataProcedure()
    {
        /** @var Design $design */
        $design = $this->SL->getService(Design::class);
        /** @var Request $request */
        $request = $this->SL->getService(Request::class);
        /** @var Settings $settings */
        $settings = $this->SL->getService(Settings::class);
        /** @var Router $router */
        $router = $this->SL->getService(Router::class);
        /** @var EntityFactory $entityFactory */
        $entityFactory = $this->SL->getService(EntityFactory::class);
        /** @var CategoriesEntity $categoriesEntity */
        $categoriesEntity = $entityFactory->get(CategoriesEntity::class);
        /** @var PagesEntity $pagesEntity */
        $pagesEntity = $entityFactory->get(PagesEntity::class);

        $pages = $pagesEntity->find(['visible'=>1]);
        $design->assign('pages', $pages);
        
        // Передаем стили и скрипты в шаблон
        $templateConfig = $this->SL->getService(TemplateConfig::class);
        $design->assign('ok_head', $templateConfig->head());
        $design->assign('ok_footer', $templateConfig->footer());

        // Передаем в дизайн название текущего роута
        $design->assign('route_name', $router->getCurrentRouteName());
        $design->assign('current_page', $request->get('page'));

        // Передаем переводы
        $design->assign('lang', $this->SL->getService(FrontTranslations::class));

        $design->assign('settings',   $this->SL->getService(Settings::class));
        $design->assign('config',     $this->SL->getService(Config::class));
        $design->assign('rootUrl',    $request->getRootUrl());

        $design->assign('is_mobile',  $design->isMobile());
        $design->assign('is_tablet',  $design->isTablet());

        $design->assign('language',   $this->getCurrentLanguage());
        $design->assign('languages',  $this->getAllLanguages());

        $design->assign('base',       $request->getRootUrl());

        $design->assign('cart',       $this->SL->getService(Cart::class)->get());
        $design->assign('wishlist',   $this->SL->getService(WishList::class)->get());
        $design->assign('comparison', $this->SL->getService(Comparison::class)->get());

        $design->assign('page',       $this->getCurrentPage());
        
        $design->assign('currencies', $this->getAllCurrencies());
        $design->assign('currency',   $this->getCurrentCurrency());

        $design->assign('user',       $this->getCurrentUser());
        $design->assign('group',      $this->getCurrentUserGroup());
        
        // Категории товаров
        $allCategories = $categoriesEntity->find();
        $this->countVisible($categoriesEntity->getCategoriesTree(), $allCategories);
        $design->assign('categories', $categoriesEntity->getCategoriesTree());

        $design->assign('js_custom_socials', $this->SL->getService(JsSocial::class)->getCustomSocials());

        // Передаем счетчики
        $counters = [];
        if (!empty($settings->get('counters'))) {
            foreach ($settings->get('counters') as $c) {
                $counters[$c->position][] = $c;
            }
        }
        $design->assign('counters', $counters);
        
        // Передаем менюшки
        $menuEntity = $entityFactory->get(MenuEntity::class);
        $menuItemsEntity = $entityFactory->get(MenuItemsEntity::class);
        $menus = $menuEntity->find(['visible' => 1]);
        if (!empty($menus)) {
            foreach ($menus as $menu) {
                $design->assign("menu", $menu);
                $all_menu_items = $menuItemsEntity->getMenuItems();
                $this->countVisible($menuItemsEntity->getMenuItemsTree((int)$menu->id), $all_menu_items, 'submenus');
                $design->assign("menu_items", $menuItemsEntity->getMenuItemsTree((int)$menu->id));
                $design->assign(MenuEntity::MENU_VAR_PREFIX . $menu->group_id, $design->fetch("menu.tpl"));
            }
        }
        
        // Передаем текущий контроллер
        if ($route = $router->getRouteByName($router->getCurrentRouteName())) {
            //$reflector = new \ReflectionClass($route['params']['controller']);
            $design->assign('controller', $route['params']['controller']);
        }

        // Передаем все что нам пришло постом, обратно в дизайн, будем из него считывать.
        if (!empty($_POST)) {
            $requestData = [];
            foreach (array_keys($_POST) as $field) {
                $requestData[$field] = $request->post($field);
            }
            $design->assign('request_data', $requestData);
        }

        QueueExtender::execute(__METHOD__, null, func_get_args());
    }

    /**
     * Метод возвращает все языки сайта, с урлами
     * 
     * @return array
     * @throws \Exception
     */
    public function getAllLanguages()
    {
        foreach ($this->allLanguages as $l) {
            $l->url = $this->getLangUlr($l->id);
        }
        return ExtenderFacade::execute(__METHOD__, $this->allLanguages, func_get_args());
    }

    /**
     * Метод возвращает текущий язык сайта с урлом
     * 
     * @return object|null
     * @throws \Exception
     */
    public function getCurrentLanguage()
    {
        $this->currentLanguage->url = $this->getLangUlr($this->currentLanguage->id);
        return ExtenderFacade::execute(__METHOD__, $this->currentLanguage, func_get_args());
    }

    /**
     * Метод возвращает все активные валюты сайта
     * 
     * @return array
     */
    public function getAllCurrencies()
    {
        return ExtenderFacade::execute(__METHOD__, $this->allCurrencies, func_get_args());
    }

    /**
     * Метод возвращает текущую валюту пользователя
     * 
     * @return mixed|void|null
     */
    public function getCurrentCurrency()
    {
        return ExtenderFacade::execute(__METHOD__, $this->currentCurrency, func_get_args());
    }

    /**
     * Метод возвращает текущую страницу сайта
     * 
     * @return object|null
     */
    public function getCurrentPage()
    {
        return ExtenderFacade::execute(__METHOD__, $this->currentPage, func_get_args());
    }

    /**
     * Метод возвращает текущего пользователя, если он залогинен
     * 
     * @return object|null
     */
    public function getCurrentUser()
    {
        return ExtenderFacade::execute(__METHOD__, $this->currentUser, func_get_args());
    }

    /**
     * Метод возвращает группу текущего пользователя, если он залогинен и принадлежит какой-то группе
     *
     * @return object|null
     */
    public function getCurrentUserGroup()
    {
        return ExtenderFacade::execute(__METHOD__, $this->currentUserGroup, func_get_args());
    }

    /**
     * Метод возвращает урл текущей страницы для другого языка, указанного как $langId
     * 
     * @param int $langId ID языка для которого генерируем урл
     * @return string
     * @throws \Exception
     */
    private function getLangUlr($langId)
    {
        /** @var Router $router */
        $router = $this->SL->getService(Router::class);
        $routeParams = $router->getCurrentRouteRequiredParams();
        $route = $router->generateUrl($router->getCurrentRouteName(), $routeParams, true, $langId);
        return ExtenderFacade::execute(__METHOD__, $route, func_get_args());
    }

    /**
     * Метод подготавливает данные для отображения их в динамических js файлах
     * Это результаты компиляции файлов scripts.tpl и common_js.tpl
     */
    public function activateDynamicJs()
    {

        /** @var Router $router */
        $router = $this->SL->getService(Router::class);
        
        // Если пришли не за скриптом, очищаем все переменные для динамического JS
        if (($routeName = $router->getCurrentRouteName()) != 'dynamic_js') {
            unset($_SESSION['dynamic_js']);
            $route = $router->getRouteByName($routeName);
            $_SESSION['dynamic_js']['controller'] = $route['params']['controller'];
        }

        if (($routeName = $router->getCurrentRouteName()) != 'common_js') {
            $route = $router->getRouteByName($routeName);
            $_SESSION['common_js']['controller'] = $route['params']['controller'];
        }
    }

    /**
     * Метод проверяет есть ли запрос prg_seo_hide (это поле постом)
     * Если есть, метод редиректит нас на указанный урл
     *
     * PRG (Post/Redirect/Get) используется в данном случае для закрытия некоторых ссылок на сайте.
     * Это достигается тем, что на сайте вместо ссылки на нужную страницу можно поставить форму
     * с типом POST и одним инпутом name="prg_seo_hide" где в значении можно указать АБСОЛЮТНЫЙ путь
     * куда нужно перенаправить пользователя
     *
     * @throws \Exception
     */
    public function activatePRG()
    {
        /** @var Request $request */
        $request = $this->SL->getService(Request::class);
        /** @var Response $response */
        $response = $this->SL->getService(Response::class);

        if ($prgSeoHide = $request->post("prg_seo_hide")) {
            $response->redirectTo($prgSeoHide);
            exit;
        }
    }
    
    /**
     * Метод устанавливает директорию, с которой нужно брать файлы шаблона (модуль или стандартный путь)
     *
     * @throws \Exception
     */
    public function configureTemplateDirProcedure()
    {
        /** @var Module $module */
        $module = $this->SL->getService(Module::class);
        /** @var Design $design */
        $design = $this->SL->getService(Design::class);
        /** @var Router $router */
        $router = $this->SL->getService(Router::class);

        if ($route = $router->getRouteByName($router->getCurrentRouteName())) {
            //$reflector = new \ReflectionClass($route['params']['controller']);

            // Сменим директорию шаблонов на директорию модуля
            if ($module->isModuleController($route['params']['controller'])) {
                $moduleTemplateDir = $module->generateModuleTemplateDir(
                    $module->getVendorName($route['params']['controller']),
                    $module->getModuleName($route['params']['controller'])
                );

                $design->setModuleTemplatesDir($moduleTemplateDir);
                $design->useModuleDir();
            }
        }
    }


    /**
     * Подсчет количества видимых дочерних элементов
     * 
     * @param array $items
     * @param $allItems
     * @param string $subItemsName
     */
    private function countVisible(array $items, $allItems, $subItemsName = 'subcategories')
    {
        foreach ($items as $item) {
            if (isset($allItems[$item->parent_id]) && !isset($allItems[$item->parent_id]->count_children_visible)) {
                $allItems[$item->parent_id]->count_children_visible = 0;
            }
            if ($item->parent_id && $item->visible) {
                $allItems[$item->parent_id]->count_children_visible++;
            }
            if (isset($item->{$subItemsName})) {
                $this->countVisible($item->{$subItemsName}, $allItems, $subItemsName);
            }
        }
    }
    
}