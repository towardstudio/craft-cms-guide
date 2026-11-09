<?php

namespace towardstudio\cmsguide\services;

use Craft;
use craft\base\Component;
use craft\enums\CmsEdition;
use craft\helpers\FileHelper;
use craft\helpers\UrlHelper;
use craft\web\View;
use Symfony\Component\Yaml\Yaml;
use towardstudio\cmsguide\CmsGuide;
use yii\helpers\Markdown;

/**
 * Loads guide pages written in Markdown.
 *
 * Generic pages ship with the plugin (src/content). Each site can add its own
 * pages, or replace a generic page, in its cms-guide folder:
 *
 *   cms-guide/intro.md             Shown at the top of the guide's home page
 *   cms-guide/pages/<slug>.md      Extra pages, or replacements for generic ones
 *   cms-guide/sections/<handle>.md Notes for a section
 *   cms-guide/blocks/<handle>.md   Notes for a content block (entry type)
 */
class Content extends Component
{
    private ?array $_guidePages = null;
    private ?array $_pluginPages = null;

    /**
     * Generic "using the CMS" pages, with any site replacements applied.
     */
    public function getGuidePages(): array
    {
        if ($this->_guidePages !== null) {
            return $this->_guidePages;
        }

        $hidden = CmsGuide::getInstance()->getSettings()->hiddenPages;
        $pages = $this->loadFolder($this->pluginContentPath('guide'), 'generic');

        foreach ($this->loadFolder($this->siteContentPath('pages'), 'site') as $slug => $page) {
            $pages[$slug] = $page;
        }

        $pages = array_filter($pages, fn($page) => !in_array($page['slug'], $hidden, true));
        $pages = array_map(fn($page) => $page + ['path' => 'guide/' . $page['slug']], $pages);
        uasort($pages, fn($a, $b) => [$a['order'], $a['title']] <=> [$b['order'], $b['title']]);

        return $this->_guidePages = $pages;
    }

    public function getGuidePage(string $slug): ?array
    {
        return $this->getGuidePages()[$slug] ?? null;
    }

    /**
     * Pages for plugins that are installed on this site.
     *
     * A site file in cms-guide/plugins/ with the same slug as a generic page is
     * attached to it as `siteNotes` and shown below the generic content. A site
     * file with no generic match becomes a page of its own.
     */
    public function getPluginPages(): array
    {
        if ($this->_pluginPages !== null) {
            return $this->_pluginPages;
        }

        $pages = $this->loadFolder($this->pluginContentPath('plugins'), 'generic');

        foreach ($this->loadFolder($this->siteContentPath('plugins'), 'site') as $slug => $sitePage) {
            if (isset($pages[$slug])) {
                $pages[$slug]['siteNotes'] = $sitePage;
            } else {
                $pages[$slug] = $sitePage;
            }
        }

        $installed = CmsGuide::getInstance()->schema->getEnabledPluginHandles();
        $pages = array_filter(
            $pages,
            fn($page) => in_array($page['plugin'] ?? $page['slug'], $installed, true)
        );
        $pages = array_map(fn($page) => $page + ['path' => 'plugins/' . $page['slug']], $pages);
        uasort($pages, fn($a, $b) => strcasecmp($a['title'], $b['title']));

        return $this->_pluginPages = $pages;
    }

    public function getPluginPage(string $slug): ?array
    {
        return $this->getPluginPages()[$slug] ?? null;
    }

    public function getSiteIntro(): ?array
    {
        return $this->loadFile($this->siteContentPath() . DIRECTORY_SEPARATOR . 'intro.md', 'site');
    }

    public function getSectionNotes(string $handle): ?array
    {
        return $this->loadFile($this->siteContentPath('sections') . DIRECTORY_SEPARATOR . $handle . '.md', 'site');
    }

    public function getBlockNotes(string $handle): ?array
    {
        return $this->loadFile($this->siteContentPath('blocks') . DIRECTORY_SEPARATOR . $handle . '.md', 'site');
    }

    /**
     * Map of field type class => generic page that explains it, from each page's
     * `fieldTypes` front matter. Lets field tables link to the right help page.
     */
    public function getFieldTypeHelp(): array
    {
        $map = [];
        foreach ([...$this->getGuidePages(), ...$this->getPluginPages()] as $page) {
            foreach ($page['fieldTypes'] ?? [] as $class) {
                $map[ltrim($class, '\\')] = $page;
            }
        }
        return $map;
    }

    /**
     * Renders a guide string: Twig first (for site name, URLs and so on), then Markdown.
     */
    public function render(string $markdown): string
    {
        $markdown = Craft::$app->getView()->renderString($markdown, $this->variables(), View::TEMPLATE_MODE_CP);
        return Markdown::process($markdown, 'gfm');
    }

    /**
     * Renders field instructions the same way Craft does in the edit screen.
     */
    public function renderInstructions(?string $instructions): ?string
    {
        if ($instructions === null || trim($instructions) === '') {
            return null;
        }
        return Markdown::process(str_replace("\r\n", "\n", $instructions), 'gfm-comment');
    }

    public function variables(): array
    {
        $site = Craft::$app->getSites()->getPrimarySite();
        $publicPath = CmsGuide::getInstance()->getSettings()->publicPath;

        return [
            'siteName' => $site->getName(),
            'siteUrl' => $site->getBaseUrl(),
            'loginUrl' => UrlHelper::cpUrl('login'),
            'guideUrl' => UrlHelper::cpUrl('cms-guide'),
            'publicGuideUrl' => $publicPath ? UrlHelper::siteUrl($publicPath) : null,
            // Craft Solo allows a single user account, so some advice doesn't apply.
            'singleUser' => Craft::$app->edition === CmsEdition::Solo,
            // Handles of installed, enabled plugins, e.g. {% if 'retour' in plugins %}
            'plugins' => CmsGuide::getInstance()->schema->getEnabledPluginHandles(),
            // Whether a redirect is created automatically when an entry's address changes
            'autoRedirects' => $this->autoRedirectsEnabled(),
        ];
    }

    private function autoRedirectsEnabled(): bool
    {
        $retour = Craft::$app->getPlugins()->getPlugin('retour');
        return $retour !== null && (bool)($retour->getSettings()->createUriChangeRedirects ?? false);
    }

    private function loadFolder(?string $path, string $source): array
    {
        if (!$path || !is_dir($path)) {
            return [];
        }

        $pages = [];
        foreach (FileHelper::findFiles($path, ['only' => ['*.md'], 'recursive' => false]) as $file) {
            if ($page = $this->loadFile($file, $source)) {
                $pages[$page['slug']] = $page;
            }
        }

        return $pages;
    }

    private function loadFile(string $file, string $source): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $raw = str_replace("\r\n", "\n", file_get_contents($file));
        $meta = [];
        $body = $raw;

        if (preg_match('/^---\n(.*?)\n---\n(.*)$/s', $raw, $matches)) {
            $meta = Yaml::parse($matches[1]) ?? [];
            $body = $matches[2];
        }

        // Filenames may start with a number to set order, e.g. 10-editing-entries.md
        $name = pathinfo($file, PATHINFO_FILENAME);
        $slug = preg_replace('/^\d+-/', '', $name);
        preg_match('/^(\d+)-/', $name, $orderMatch);

        return array_merge([
            'title' => ucfirst(str_replace('-', ' ', $slug)),
            'order' => isset($orderMatch[1]) ? (int)$orderMatch[1] : 999,
            'status' => 'published',
        ], $meta, [
            'slug' => $slug,
            'source' => $source,
            'body' => $body,
        ]);
    }

    private function pluginContentPath(string $folder): string
    {
        return CmsGuide::getInstance()->getBasePath() . DIRECTORY_SEPARATOR . 'content' . DIRECTORY_SEPARATOR . $folder;
    }

    private function siteContentPath(?string $folder = null): ?string
    {
        $base = Craft::getAlias(CmsGuide::getInstance()->getSettings()->contentPath);
        if (!$base) {
            return null;
        }
        return $folder ? $base . DIRECTORY_SEPARATOR . $folder : $base;
    }
}
