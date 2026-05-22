# dcawizard Contao Extension

A backend widget for Contao that allows editors to manage records from a foreign database table directly within the parent record's edit form.

---

## Installation

Install via Composer:

```bash
composer require terminal42/dcawizard
```

---

## Basic Usage (DCA Example)

```php
'prices' => [
    'inputType'    => 'dcaWizard',
    'foreignTable' => 'tl_iso_prices',
    'eval' => [
        'fields' => ['id', 'name', 'alias'],
    ],
],
```

### Supporting both versions simultaneously

If your project needs to support both Contao 5.6 and 5.7+, you can allow both major
versions using a version union constraint in your `composer.json`:
```json
"require": {
    "terminal42/dcawizard": "^3.0 || ^4.0"
}
```

Composer will then automatically install:
- `3.x` when the project uses Contao ≤ 5.6
- `4.x` when the project uses Contao ≥ 5.7

No code changes are needed on your end — the correct version will be resolved
based on the Contao version constraint in your own `composer.json`.

If you want to support both 3.x and 4.x simultaneously with a custom template,
you need to set `customTpl` dynamically at runtime rather than statically in the DCA,
since the template name changed between versions. For example, you can use an [attributes callback](https://docs.contao.org/5.x/dev/reference/dca/callbacks/#fields-field-attributes)
to detect the installed version and return the correct template name:

```php
<?php

use Composer\InstalledVersions;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;

#[AsCallback('tl_my_table', 'fields.my_field.attributes')]
class MyFieldAttributesListener
{
    public function __invoke(array $attributes): array
    {
        $isNewVersion = version_compare(InstalledVersions::getVersion('terminal42/dcawizard'), '4.0', '>=');

        $attributes['eval']['customTpl'] = $isNewVersion ? 'backend/widget/dcawizard_custom': 'be_widget_dcawizard_custom';

        return $attributes;
    }
}
```

---

## Migrating from 3.x to 4.x

The 4.x release is largely backwards compatible.

The `list_callback` option has been removed. To customize the record list rendering, override the `{% block record %}`
block in a custom template that extends the default one:

```twig
{# contao/templates/backend/widget/dcawizard_custom.html.twig #}
{% extends '@Contao/backend/widget/dcawizard.html.twig' %}

{% block record_value %}
    {# your custom rendering here #}
{% endblock %}
```

Then reference your template via `customTpl`:
```php
'eval' => [
    'customTpl' => 'backend/widget/dcawizard_custom',
],
```

The default template name has also been renamed. If you have overridden it in your project, update it accordingly:

| Version 3.x (.html5)  | Version 4.x (.html.twig)          |
|-----------------------|-----------------------------------|
| `be_widget_dcawizard` | `backend/widget/dcawizard_custom` |

---

## Configuration Reference

### Top-level DCA Options

These options are set directly on the field definition, outside of `eval`.

#### Foreign Table Options

| Option                  | Type     | Default | Description                                                                   |
|-------------------------|----------|---------|-------------------------------------------------------------------------------|
| `foreignTable`          | `string` | -       | The foreign database table to manage records from.                            |
| `foreignField`          | `string` | `'pid'` | The foreign key field linking child records to the parent (e.g. `fid`).       |
| `foreignTable_callback` | `array`  | -       | Callback to dynamically determine the foreign table name.                     |
| `useParentTable`        | `bool`   | false   | Add the parent table name to the database field "ptable", limit backend view  |
| `useParentField`        | `bool`   | false   | Add the parent field name to the database field "pfield", limit backend view  |

#### URL Params Options

| Option   | Type    | Default | Description                                                                          |
|----------|---------|---------|--------------------------------------------------------------------------------------|
| `params` | `array` | `[]`    | Additional URL parameters added to the popup link (e.g. `do`, custom filter fields). |

---

### `eval` Options

These options are set inside the `eval` array of the field definition.

#### Display Options

| Option         | Type       | Default | Description                                                     |
|----------------|------------|---------|-----------------------------------------------------------------|
| `fields`       | `string[]` | -       | Fields to display as columns in the record list.                |
| `headerFields` | `string[]` | `[]`    | Custom column header labels. Leave empty to use field labels.   |
| `orderField`   | `string`   | -       | Field (and direction) used to order records (e.g. `name DESC`). |
| `emptyLabel`   | `string`   | -       | Label displayed when no child records exist.                    |

#### Button Options

| Option            | Type     | Default | Description                                                |
|-------------------|----------|---------|------------------------------------------------------------|
| `editButtonLabel` | `string` | -       | Custom label for the edit/open popup button.               |
| `hideButton`      | `bool`   | `false` | Hides the popup button (record list display only).         |

#### Operations Options

| Option              | Type       | Default | Description                                                       |
|---------------------|------------|---------|-------------------------------------------------------------------|
| `showOperations`    | `bool`     | `false` | Displays per-row operation buttons (edit, delete, etc.).          |
| `operations`        | `string[]` | all     | Limits which row operations are shown. Defaults to all available. |
| `global_operations` | `string[]` | `[]`    | Global operations added above the list (e.g. `['new']`).          |

#### Template Options

| Option      | Type     | Default               | Description                          |
|-------------|----------|-----------------------|--------------------------------------|
| `customTpl` | `string` | `be_widget_dcawizard` | Custom backend widget template name. |

---

## Full Configuration Example

```php
'prices' => [
    'inputType' => 'dcaWizard',

    // Define the foreign table
    'foreignTable' => 'tl_iso_prices',

    // Define the foreign field (e.g. fid instead of pid)
    'foreignField' => 'fid',

    // Use a callback to determine the foreign table dynamically
    'foreignTable_callback' => ['tl_iso_prices', 'getTableName'],

    // Add special params to the popup button link
    'params' => [
        'do'          => 'member',
        'filterField' => 'group',
    ],

    'eval' => [
        // Fields to display in the list columns
        'fields' => ['id', 'name', 'alias'],

        // Custom column headers (leave empty to use field labels)
        'headerFields' => ['ID', 'Name', 'Alias'],

        // Custom label for the edit/open button
        'editButtonLabel' => $GLOBALS['TL_LANG']['tl_iso_products']['prices_edit_button'],

        // Label shown when no records exist
        'emptyLabel' => $GLOBALS['TL_LANG']['tl_iso_products']['prices_empty_label'],

        // Order records by a specific field
        'orderField' => 'name DESC',

        // Hide the popup button (show list only)
        'hideButton' => true,

        // Show per-row operations
        'showOperations' => true,

        // Limit which operations appear per row
        'operations' => ['edit', 'delete', 'new'],

        // Add global operations above the list
        'global_operations' => ['new'],

        // Use a custom widget template
        'customTpl' => 'backend/widget/dcawizard_custom',
    ],
],
```

---

## Usage with DC_Multilingual

If the foreign table is managed by [DC_Multilingual](https://github.com/terminal42/contao-DC_Multilingual), use the dedicated input type to exclude translated duplicate entries from the list:

```php
'inputType' => 'dcaWizardMultilingual',
```

If your setup uses a custom column name instead of the default `language` column (see `langColumn` in DC_Multilingual), specify it in the `eval` section:

```php
'eval' => [
    'langColumn' => 'language_column_name',
],
```

---

## Best Practices

- Use `foreignField` when the linking column is not the default `pid`.
- Use `foreignTable_callback` for dynamic table resolution (e.g. based on parent record type).
- Use `params` to pre-filter the child record list via URL parameters.
- Provide `headerFields` matching the count of `fields` for clarity.
- Enable `showOperations` only when inline editing of individual rows is needed.
