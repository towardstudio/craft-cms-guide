<?php

namespace towardstudio\cmsguide;

use craft\base\Model;
use craft\base\Plugin;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use towardstudio\cmsguide\models\Settings;
use towardstudio\cmsguide\services\Content;
use towardstudio\cmsguide\services\Schema;
use yii\base\Event;

/**
 * @property-read Content $content
 * @property-read Schema $schema
 * @method Settings getSettings()
 */
class CmsGuide extends Plugin
{
    public bool $hasCpSection = true;
    public bool $hasCpSettings = false;

    public static function config(): array
    {
        return [
            'components' => [
                'content' => Content::class,
                'schema' => Schema::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $event->rules['cms-guide'] = 'cms-guide/guide/index';
                $event->rules['cms-guide/guide/<slug:[\w\-]+>'] = 'cms-guide/guide/page';
                $event->rules['cms-guide/pages/<slug:[\w\-]+>'] = 'cms-guide/guide/site-page';
                $event->rules['cms-guide/plugins/<slug:[\w\-]+>'] = 'cms-guide/guide/plugin';
                $event->rules['cms-guide/sections/<handle:[\w\-]+>'] = 'cms-guide/guide/section';
                $event->rules['cms-guide/blocks/<handle:[\w\-]+>'] = 'cms-guide/guide/block';
            }
        );

        // Public, memorable route on the website that sends people to the guide.
        // Guests are shown the control panel login first, then returned to the guide.
        $publicPath = $this->getSettings()->publicPath;
        if ($publicPath) {
            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                function (RegisterUrlRulesEvent $event) use ($publicPath) {
                    $event->rules[$publicPath] = 'cms-guide/redirect/index';
                }
            );
        }
    }

    public function getCpNavItem(): ?array
    {
        $item = parent::getCpNavItem();
        $item['label'] = $this->getSettings()->navLabel;
        $item['icon'] = 'book';
        return $item;
    }

    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }
}
