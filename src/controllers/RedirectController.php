<?php

namespace towardstudio\cmsguide\controllers;

use craft\helpers\UrlHelper;
use craft\web\Controller;
use yii\web\Response;

/**
 * Public route on the website (e.g. /cms-guide) that sends people to the guide.
 * Craft shows the control panel login to anyone not signed in, then returns them here.
 */
class RedirectController extends Controller
{
    protected array|bool|int $allowAnonymous = ['index'];

    public function actionIndex(): Response
    {
        return $this->redirect(UrlHelper::cpUrl('cms-guide'));
    }
}
