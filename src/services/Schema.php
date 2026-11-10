<?php

namespace towardstudio\cmsguide\services;

use Craft;
use craft\base\Component;
use craft\base\FieldInterface;
use craft\elements\Entry;
use craft\fieldlayoutelements\BaseField;
use craft\fieldlayoutelements\CustomField;
use craft\fields\Matrix;
use craft\helpers\UrlHelper;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\Section;
use towardstudio\cmsguide\CmsGuide;

/**
 * Reads the site's live content model (sections, entry types, fields) and
 * turns it into plain arrays the guide templates can render.
 */
class Schema extends Component
{
    private ?array $_blockUsage = null;
    private ?array $_sections = null;

    /**
     * Sections in the same order as the Entries sidebar in the control panel.
     *
     * Uses Craft's resolved element sources, so it follows any custom order set
     * under Entries > Customize sources, and plugins that change the list
     * (e.g. Expanded Singles listing each single on its own). Sections that
     * aren't in the sidebar are added at the end, in Craft's default order.
     *
     * @return Section[]
     */
    public function getSections(): array
    {
        if ($this->_sections !== null) {
            return $this->_sections;
        }

        $hidden = CmsGuide::getInstance()->getSettings()->hiddenSections;
        $remaining = [];
        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            if (!in_array($section->handle, $hidden, true)) {
                $remaining[$section->uid] = $section;
            }
        }

        $ordered = [];
        $sources = Craft::$app->getElementSources()->getSources(Entry::class);
        $this->collectSectionsFromSources($sources, $remaining, $ordered);

        return $this->_sections = [...$ordered, ...array_values($remaining)];
    }

    /**
     * @param array[] $sources
     * @param Section[] $remaining Sections not yet placed, by UID. Placed ones are removed.
     * @param Section[] $ordered
     */
    private function collectSectionsFromSources(array $sources, array &$remaining, array &$ordered): void
    {
        foreach ($sources as $source) {
            $key = $source['key'] ?? '';

            if ($key === 'singles') {
                // Craft's combined "Singles" source: list singles alphabetically, as Craft does
                $singles = array_filter($remaining, fn(Section $s) => $s->type === Section::TYPE_SINGLE);
                uasort($singles, fn(Section $a, Section $b) => strcasecmp($a->name, $b->name));
                foreach ($singles as $uid => $section) {
                    $ordered[] = $section;
                    unset($remaining[$uid]);
                }
            } elseif (preg_match('/^(?:section|single):(.+)$/', $key, $match) && isset($remaining[$match[1]])) {
                $ordered[] = $remaining[$match[1]];
                unset($remaining[$match[1]]);
            }

            if (!empty($source['nested'])) {
                $this->collectSectionsFromSources($source['nested'], $remaining, $ordered);
            }
        }
    }

    public function getSection(string $handle): ?array
    {
        $section = Craft::$app->getEntries()->getSectionByHandle($handle);

        if (!$section || in_array($handle, CmsGuide::getInstance()->getSettings()->hiddenSections, true)) {
            return null;
        }

        return [
            'name' => $section->name,
            'handle' => $section->handle,
            'type' => $section->type,
            'typeSummary' => $this->sectionTypeSummary($section),
            'cpUrl' => $this->sectionCpUrl($section),
            'entryTypes' => array_map(
                fn(EntryType $type) => $this->describeEntryType($type),
                $this->visibleEntryTypes($section->getEntryTypes())
            ),
        ];
    }

    /**
     * Entry types used as content blocks inside Matrix fields, for the nav.
     * Blocks that only exist inside another block (e.g. the items in a grid)
     * are nested under that block rather than listed on their own.
     */
    public function getBlocks(): array
    {
        $usage = $this->blockUsage();
        $byHandle = [];

        foreach (array_keys($usage) as $handle) {
            $type = Craft::$app->getEntries()->getEntryTypeByHandle($handle);
            if ($type && $this->isVisible($type)) {
                $byHandle[$handle] = ['name' => $type->name, 'handle' => $handle, 'children' => []];
            }
        }

        $topLevel = [];
        foreach ($byHandle as $handle => $block) {
            $parents = array_filter($usage[$handle], fn($place) => $place['parentIsBlock']);
            if (count($parents) < count($usage[$handle])) {
                $topLevel[$handle] = $handle;
            }
        }

        foreach ($byHandle as $handle => $block) {
            if (isset($topLevel[$handle])) {
                continue;
            }
            foreach ($usage[$handle] as $place) {
                if (isset($byHandle[$place['parentHandle']])) {
                    $byHandle[$place['parentHandle']]['children'][$handle] = $block;
                }
            }
        }

        $blocks = array_map(fn($handle) => $byHandle[$handle], $topLevel);
        uasort($blocks, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $blocks;
    }

    public function getBlock(string $handle): ?array
    {
        $type = Craft::$app->getEntries()->getEntryTypeByHandle($handle);

        if (!$type || !$this->isVisible($type) || !isset($this->blockUsage()[$handle])) {
            return null;
        }

        $block = $this->describeEntryType($type);
        $block['usedIn'] = $this->blockUsage()[$handle];

        return $block;
    }

    /**
     * Handle-indexed list of the plugins installed and enabled on this site.
     */
    public function getEnabledPluginHandles(): array
    {
        return array_keys(Craft::$app->getPlugins()->getAllPlugins());
    }

    public function describeEntryType(EntryType $type): array
    {
        return [
            'name' => $type->name,
            'handle' => $type->handle,
            'description' => $type->description,
            'tabs' => $this->describeLayout($type->getFieldLayout()),
        ];
    }

    private function describeLayout(FieldLayout $layout): array
    {
        $tabs = [];

        foreach ($layout->getTabs() as $tab) {
            $fields = [];

            foreach ($tab->getElements() as $element) {
                if (!$element instanceof BaseField) {
                    continue;
                }
                $fields[] = $this->describeField($element);
            }

            if ($fields) {
                $tabs[] = ['name' => $tab->name, 'fields' => $fields];
            }
        }

        return $tabs;
    }

    private function describeField(BaseField $element): array
    {
        $field = $element instanceof CustomField ? $element->getField() : null;

        return [
            'label' => $element->label(),
            'instructions' => $element->instructions ?: $field?->instructions,
            'required' => (bool)$element->required,
            'typeName' => $field ? $this->fieldTypeName($field) : $this->nativeFieldTypeName($element),
            'typeClass' => $field ? get_class($field) : get_class($element),
            'blocks' => $field instanceof Matrix ? $this->describeMatrix($field) : null,
        ];
    }

    private function describeMatrix(Matrix $field): array
    {
        return [
            'viewMode' => $field->viewMode,
            'buttonLabel' => $field->createButtonLabel ?: Craft::t('app', 'New entry'),
            'min' => $field->minEntries,
            'max' => $field->maxEntries,
            'types' => array_map(
                fn(EntryType $type) => [
                    'name' => $type->name,
                    'handle' => $type->handle,
                    'isBlock' => isset($this->blockUsage()[$type->handle]),
                ],
                $this->visibleEntryTypes($field->getEntryTypes())
            ),
        ];
    }

    /**
     * Map of block entry type handle => places it can be added.
     */
    private function blockUsage(): array
    {
        if ($this->_blockUsage !== null) {
            return $this->_blockUsage;
        }

        $usage = [];
        $entries = Craft::$app->getEntries();
        $blockFieldIds = $this->blockFieldIds();
        $blockHandles = $this->blockHandles($blockFieldIds);

        // Editors know sections ("Pages"), not entry types ("Default"), so name
        // top-level parents after the section(s) that use them.
        $sectionNames = [];
        foreach ($this->getSections() as $section) {
            foreach ($section->getEntryTypes() as $type) {
                $sectionNames[$type->handle][] = $section->name;
            }
        }

        foreach ($entries->getAllEntryTypes() as $parentType) {
            $parentIsBlock = isset($blockHandles[$parentType->handle]);

            foreach ($parentType->getFieldLayout()->getCustomFieldElements() as $element) {
                $field = $element->getField();
                if (!$field instanceof Matrix) {
                    continue;
                }
                // Only a block field itself, or a Matrix field inside a block, counts
                if (!$parentIsBlock && !isset($blockFieldIds[$field->id])) {
                    continue;
                }
                foreach ($field->getEntryTypes() as $blockType) {
                    $usage[$blockType->handle][] = [
                        'fieldLabel' => $element->label(),
                        'parentName' => $parentType->name,
                        'sectionNames' => $sectionNames[$parentType->handle] ?? [],
                        'parentHandle' => $parentType->handle,
                        'parentIsBlock' => $parentIsBlock,
                    ];
                }
            }
        }

        return $this->_blockUsage = $usage;
    }

    /**
     * IDs of the Matrix fields named in the blockFields setting, keyed by ID.
     */
    private function blockFieldIds(): array
    {
        $ids = [];
        foreach (CmsGuide::getInstance()->getSettings()->blockFields as $handle) {
            $field = Craft::$app->getFields()->getFieldByHandle($handle);
            if ($field instanceof Matrix) {
                $ids[$field->id] = true;
            }
        }

        return $ids;
    }

    /**
     * Handles of every entry type that counts as a block: those in the block
     * fields, plus those in Matrix fields inside a block (e.g. grid items), at any depth.
     */
    private function blockHandles(array $blockFieldIds): array
    {
        $queue = [];
        foreach (array_keys($blockFieldIds) as $id) {
            $field = Craft::$app->getFields()->getFieldById($id);
            foreach ($field->getEntryTypes() as $type) {
                $queue[] = $type;
            }
        }

        $handles = [];
        while ($type = array_shift($queue)) {
            if (isset($handles[$type->handle])) {
                continue;
            }
            $handles[$type->handle] = true;

            foreach ($type->getFieldLayout()->getCustomFieldElements() as $element) {
                $field = $element->getField();
                if ($field instanceof Matrix) {
                    array_push($queue, ...$field->getEntryTypes());
                }
            }
        }

        return $handles;
    }

    /**
     * @param EntryType[] $types
     * @return EntryType[]
     */
    private function visibleEntryTypes(array $types): array
    {
        return array_values(array_filter($types, fn(EntryType $type) => $this->isVisible($type)));
    }

    private function isVisible(EntryType $type): bool
    {
        return !in_array($type->handle, CmsGuide::getInstance()->getSettings()->hiddenEntryTypes, true);
    }

    /**
     * Editor-friendly names for field types. Falls back to Craft's own name.
     */
    private function fieldTypeName(FieldInterface $field): string
    {
        return match (get_class($field)) {
            'craft\fields\Assets' => 'Image or file',
            'craft\fields\Categories' => 'Category picker',
            'craft\fields\Checkboxes' => 'Tick boxes',
            'craft\fields\Color' => 'Colour',
            'craft\fields\Date' => 'Date',
            'craft\fields\Dropdown' => 'Dropdown',
            'craft\fields\Entries' => 'Entry picker',
            'craft\fields\Lightswitch' => 'On/off switch',
            'craft\fields\Link' => 'Link',
            'craft\fields\Matrix' => 'Content blocks',
            'craft\fields\MultiSelect' => 'Multiple choice',
            'craft\fields\Number' => 'Number',
            'craft\fields\PlainText' => 'Text',
            'craft\fields\RadioButtons' => 'Choose one option',
            'craft\fields\Table' => 'Table',
            'craft\fields\Tags' => 'Tags',
            'craft\ckeditor\Field' => 'Formatted text',
            'johnhenry\matrixblockanchor\fields\MatrixBlockAnchorField' => 'Anchor link',
            'nystudio107\seomatic\fields\SeoSettings' => 'Search and social settings',
            default => $field::displayName(),
        };
    }

    private function nativeFieldTypeName(BaseField $element): string
    {
        $class = (new \ReflectionClass($element))->getShortName();
        return match ($class) {
            'EntryTitleField', 'TitleField' => 'Title',
            'AssetTitleField' => 'Title',
            'AltField' => 'Alternative text',
            default => trim(preg_replace('/(?<!^)[A-Z]/', ' $0', preg_replace('/Field$/', '', $class))),
        };
    }

    /**
     * Only singles get an automatic summary. For channels and structures, whether
     * order or nesting matters depends on how the site uses them, which the schema
     * can't tell us, so that belongs in the site's cms-guide/sections notes.
     */
    private function sectionTypeSummary(Section $section): ?string
    {
        return match ($section->type) {
            Section::TYPE_SINGLE => 'There is only one of these, so you edit it rather than add new ones.',
            default => null,
        };
    }

    private function sectionCpUrl(Section $section): ?string
    {
        if ($section->type === Section::TYPE_SINGLE) {
            $entry = Entry::find()->sectionId($section->id)->status(null)->one();
            return $entry?->getCpEditUrl();
        }

        return UrlHelper::cpUrl('entries/' . $section->handle);
    }
}
