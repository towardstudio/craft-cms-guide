# CMS Guide for Craft CMS

A guide for content editors, built into the Craft control panel. It combines:

- **Generic pages** on how Craft works, written once and shared by every site.
- **Site pages** built automatically from the site's sections, content blocks and field instructions.
- **Site notes** written in Markdown in the site's own `cms-guide` folder, for anything the plugin can't work out for itself.

The guide is only visible to people logged in to the control panel. A public address (`/cms-guide` by default) sends anyone who has lost the login link to the login page, then on to the guide.

## Requirements

- Craft CMS 5
- PHP 8.2 or later

## Installation

```sh
composer require towardstudio/craft-cms-guide
php craft plugin/install cms-guide
```

Admins can see the guide straight away. Other users need the "Access CMS Guide" permission, set under Settings > Users.

## Settings

Create `config/cms-guide.php` in the site to change any of these:

```php
<?php

return [
    // Label in the control panel menu
    'navLabel' => 'Website guide',

    // Public address that sends people to the guide (via the login page). null to turn off.
    'publicPath' => 'cms-guide',

    // Folder holding this site's own guide content
    'contentPath' => '@root/cms-guide',

    // Matrix fields whose entry types are listed under "Content blocks"
    'blockFields' => ['content'],

    // Sections or entry types editors never touch, e.g. ['ctas']
    'hiddenSections' => [],
    'hiddenEntryTypes' => [],

    // Generic guide pages that don't apply to this site, by slug, e.g. ['drafts-and-revisions']
    'hiddenPages' => [],

    // Shown at the bottom of every guide page. Markdown allowed.
    'supportText' => null,
];
```

## What the guide builds for you

- **Sections** are listed in the same order as the Entries sidebar, including any custom order set under Entries > Customize sources. Each section's page lists its fields, tab by tab.
- **Content blocks** are the entry types in the Matrix fields named in `blockFields`, plus any blocks nested inside them (such as the items in a grid). Nested blocks appear under their parent. Other Matrix fields, such as hero buttons, are described on their section's page but don't get pages of their own.
- **Field instructions** come from Craft. An instruction set on a field layout overrides the field's own instruction, and fields with no instructions show "No instructions yet". Write instructions in Craft first: they help editors on the edit screen as well as in the guide.
- **Entry type descriptions** appear as the intro on block pages.

## Site content

Add a `cms-guide` folder to the site's root folder. Everything in it is optional.

| File | Shown |
| --- | --- |
| `intro.md` | At the top of the guide's home page |
| `sections/<sectionHandle>.md` | At the top of that section's page, e.g. `sections/pages.md`. Its `summary` also appears on the section's card on the guide's home page |
| `blocks/<entryTypeHandle>.md` | At the top of that block's page, e.g. `blocks/mediaText.md` |
| `blocks/<entryTypeHandle>.webp` | A screenshot of the block on the website, shown at the top of that block's page. `.png`, `.jpg`, `.gif` and `.avif` also work |
| `plugins/<slug>.md` | Below the generic page for that plugin, under a "Specific to [site name]" heading, e.g. `plugins/freeform.md`. If there's no generic page with that slug, it becomes its own page under "Extra tools". Only shown if the plugin is installed. Set `plugin: <handle>` in the front matter if the filename isn't the plugin's handle |
| `pages/<slug>.md` | As an extra page under "Using the CMS". Use the same slug as a generic page to replace it for this site |
| `images/` | Images used in any of the Markdown files |

Good uses: when to choose one block over another, image sizes for a block, content rules agreed with the client.

The guide doesn't say whether the order or nesting of entries in a channel or structure matters, because that depends on how the website uses them. If it does matter, say so in `sections/<sectionHandle>.md`.

### Front matter

All files are Markdown and can start with:

```yaml
---
title: Page title
summary: One line shown on the guide's home page, and as the intro on section pages
status: draft    # shows a "not checked yet" notice
---
```

Prefix a page's filename with a number to set its order, e.g. `pages/15-news-articles.md`.

### Variables

Files are rendered as Twig before Markdown, so these can be used in any file:

- `{{ siteName }}`, `{{ siteUrl }}`, `{{ loginUrl }}`, `{{ guideUrl }}`, `{{ publicGuideUrl }}`
- `{{ blockList }}`: a linked list of all the site's content blocks, with nested blocks under their parent

For conditional text:

- `plugins`: handles of installed plugins, e.g. `{% if 'freeform' in plugins %}...{% endif %}`
- `autoRedirects`: true if Retour is installed and set to create redirects when an entry's address changes
- `singleUser`: true on Craft Solo, which allows only one login

### Images

Use a path relative to the `cms-guide` folder and always write alt text describing what the image shows:

```markdown
![The Save button at the top right of the edit screen](images/save-button.webp)
```

Images are only served to people logged in to the control panel. A missing image shows an "Image not found" notice. Keep files small: WebP, cropped to the area that matters.

To convert screenshots, save them as PNG or JPG in the `cms-guide` folder, then run this from the site's root folder:

```sh
vendor/bin/cms-guide-images --remove-originals
```

It converts every PNG and JPG in `cms-guide/` to WebP, scales anything wider than 1600px down to 1600px and deletes the originals. Leave out `--remove-originals` to keep them, or pass a folder path to convert a different folder. Requires ImageMagick (`brew install imagemagick`).

## Writing generic content

Generic pages live in `src/content` and apply to every site, so keep them free of anything site-specific. Use the variables above for site names and links, and conditions for things that vary, such as `singleUser`.

- `src/content/guide/`: pages under "Using the CMS".
- `src/content/plugins/`: pages under "Extra tools", shown only when the plugin is installed. The filename is the plugin's handle, or set `plugin: <handle>` in the front matter.
- Images go alongside them, with paths relative to `src/content`.

Two extra front matter properties apply to generic pages:

- `plugin`: the plugin handle a plugin page depends on.
- `fieldTypes`: field type classes the page explains, e.g. `craft\fields\Matrix`. On section and block pages, fields of that type link their type name to this page.

```yaml
---
title: Images and files
fieldTypes:
  - craft\fields\Assets
---
```

## Developing the plugin

To test changes on a site before releasing them, clone this repo next to the site and add a path repository to the site's `composer.json` temporarily. Don't commit it.

```json
"repositories": [
    { "type": "path", "url": "../craft-cms-guide" }
]
```

Then run `composer require towardstudio/craft-cms-guide:@dev`. To release, update the changelog, tag a new version and run `composer update towardstudio/craft-cms-guide` on each site.
