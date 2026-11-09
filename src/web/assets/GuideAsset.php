<?php

namespace towardstudio\cmsguide\web\assets;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

class GuideAsset extends AssetBundle
{
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';
        $this->depends = [CpAsset::class];
        $this->css = ['guide.css'];

        parent::init();
    }
}
