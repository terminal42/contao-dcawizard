# API changes

## Version 2.* to 3.0.1

### `foreignTableCallback`

The `foreignTableCallback` option has been renamed to `foreignTable_callback` to makes it compatible with using
the `#[AsCallback()` attribute.


### `listCallback`

The `listCallback` option has been renamed to `list_callback` to makes it compatible with using
the `#[AsCallback()` attribute.

Additionally, the first argument of the method no longer receives a `Contao\Database\Result` but an array of rows.
