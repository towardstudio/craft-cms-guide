<?php

namespace towardstudio\cmsguide\controllers;

use craft\web\Controller;
use towardstudio\cmsguide\CmsGuide;
use yii\web\NotFoundHttpException;
use yii\web\Response;

/**
 * Serves guide images from the plugin's content folder or the site's cms-guide
 * folder. Both sit outside the web root, so images are only available to people
 * who can see the guide.
 */
class ImageController extends Controller
{
    public function actionServe(string $source, string $path): Response
    {
        $this->requirePermission('accessPlugin-cms-guide');

        $file = CmsGuide::getInstance()->content->resolveImagePath($source, $path)
            ?? throw new NotFoundHttpException('Image not found');

        $response = $this->response->sendFile($file, null, ['inline' => true]);

        // URLs include the file's modified time, so they can be cached until the image changes
        $response->getHeaders()->set('Cache-Control', 'private, max-age=31536000, immutable');

        return $response;
    }
}
