<?php

namespace towardstudio\cmsguide\controllers;

use craft\web\Controller;
use towardstudio\cmsguide\CmsGuide;
use towardstudio\cmsguide\web\assets\GuideAsset;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class GuideController extends Controller
{
    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('accessPlugin-cms-guide');
        $this->getView()->registerAssetBundle(GuideAsset::class);

        return true;
    }

    public function actionIndex(): Response
    {
        $plugin = CmsGuide::getInstance();

        return $this->renderGuide('cms-guide/index', [
            'title' => $plugin->getSettings()->navLabel,
            'intro' => $plugin->content->getSiteIntro(),
            'sections' => array_map(
                fn($section) => $plugin->schema->getSection($section->handle) + [
                    'summary' => $plugin->content->getSectionNotes($section->handle)['summary'] ?? null,
                ],
                $plugin->schema->getSections()
            ),
            'selected' => 'home',
        ]);
    }

    public function actionPage(string $slug): Response
    {
        $page = CmsGuide::getInstance()->content->getGuidePage($slug) ?? throw new NotFoundHttpException();

        return $this->renderGuide('cms-guide/page', [
            'title' => $page['title'],
            'page' => $page,
            'selected' => 'guide/' . $slug,
        ]);
    }

    public function actionSitePage(string $slug): Response
    {
        return $this->actionPage($slug);
    }

    public function actionPlugin(string $slug): Response
    {
        $page = CmsGuide::getInstance()->content->getPluginPage($slug) ?? throw new NotFoundHttpException();

        return $this->renderGuide('cms-guide/page', [
            'title' => $page['title'],
            'page' => $page,
            'selected' => 'plugins/' . $slug,
        ]);
    }

    public function actionSection(string $handle): Response
    {
        $plugin = CmsGuide::getInstance();
        $section = $plugin->schema->getSection($handle) ?? throw new NotFoundHttpException();

        return $this->renderGuide('cms-guide/section', [
            'title' => $section['name'],
            'section' => $section,
            'notes' => $plugin->content->getSectionNotes($handle),
            'selected' => 'sections/' . $handle,
        ]);
    }

    public function actionBlock(string $handle): Response
    {
        $plugin = CmsGuide::getInstance();
        $block = $plugin->schema->getBlock($handle) ?? throw new NotFoundHttpException();

        return $this->renderGuide('cms-guide/block', [
            'title' => $block['name'],
            'blockInfo' => $block,
            'notes' => $plugin->content->getBlockNotes($handle),
            'selected' => 'blocks/' . $handle,
        ]);
    }

    private function renderGuide(string $template, array $variables): Response
    {
        $plugin = CmsGuide::getInstance();

        return $this->renderTemplate($template, array_merge([
            'guide' => $plugin->content,
            'settings' => $plugin->getSettings(),
            'nav' => [
                'guide' => $plugin->content->getGuidePages(),
                'sections' => $plugin->schema->getSections(),
                'blocks' => $plugin->schema->getBlocks(),
                'plugins' => $plugin->content->getPluginPages(),
            ],
            'fieldTypeHelp' => $plugin->content->getFieldTypeHelp(),
        ], $plugin->content->variables(), $variables));
    }
}
