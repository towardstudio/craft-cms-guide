<?php

namespace towardstudio\cmsguide\models;

use craft\base\Model;

/**
 * Override any of these in the site's config/cms-guide.php file.
 */
class Settings extends Model
{
    /** Label for the control panel nav item. */
    public string $navLabel = 'Website guide';

    /** Public path on the website that redirects to the guide. Set to null to disable. */
    public ?string $publicPath = 'cms-guide';

    /** Folder holding this site's own guide content (Markdown). */
    public string $contentPath = '@root/cms-guide';

    /**
     * Handles of the Matrix fields that hold the page's content blocks. Their entry
     * types, and any nested inside them, get a page under "Content blocks".
     */
    public array $blockFields = ['content'];

    /** Section handles to leave out of the guide. */
    public array $hiddenSections = [];

    /** Entry type handles to leave out of the guide. */
    public array $hiddenEntryTypes = [];

    /** Generic page slugs to leave out of the guide. */
    public array $hiddenPages = [];

    /** Who editors should contact for help. Markdown allowed. */
    public ?string $supportText = null;
}
