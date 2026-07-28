# API changes

## Version 4.* to 5.*

The 5.x release is largely backwards compatible.

The public API methods of the widget class have changed, as well as the template markup.
If you customized the template, make sure to sync with the latest changes.


## Version 3.* to 4.*

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



## Version 2.* to 3.0.1

### `foreignTableCallback`

The `foreignTableCallback` option has been renamed to `foreignTable_callback` to makes it compatible with using
the `#[AsCallback()` attribute.


### `listCallback`

The `listCallback` option has been renamed to `list_callback` to makes it compatible with using
the `#[AsCallback()` attribute.

Additionally, the first argument of the method no longer receives a `Contao\Database\Result` but an array of rows.
