<?php


namespace Okay\Core\Routes\Strategies\AllNews;


use Okay\Core\Routes\Strategies\AbstractRouteStrategy;
use Okay\Core\Settings;
use Okay\Core\ServiceLocator;

class DefaultStrategy extends AbstractRouteStrategy
{
    private $settings;

    public function __construct()
    {
        $serviceLocator = ServiceLocator::getInstance();
        $this->settings = $serviceLocator->getService(Settings::class);
    }

    public function generateRouteParams($url)
    {
        $prefix = $this->settings->get('all_news_routes_template__default');

        if (empty($prefix)) {
            $prefix = 'news';
        }

        return ['/'.$prefix, [], ['{$typePost}' => 'news']];
    }
}