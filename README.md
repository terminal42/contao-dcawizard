dcawizard Contao Extension
==========================

This extension provides a widget to handle the external table records.

How to use:

```php
// DCA defnition
'prices' => array
(
    'label'                 => &$GLOBALS['TL_LANG']['tl_iso_products']['prices'],
    'inputType'             => 'dcaWizard',

    // Define the foreign table
    'foreignTable'          => 'tl_iso_prices',

    // Use the callback to determine the foreign table
    'foreignTableCallback'  => array('tl_iso_prices', 'getTableName'),

    'eval'                  => array
    (
        // A list of fields to be displayed in the table
        'fields' => array('id', 'name', 'alias'),

        // Header fields of the table (leave empty to use labels)
        'headerFields' => array('ID', 'Name', 'Alias'),

        // Use a custom label for the edit button
        'editButtonLabel' => $GLOBALS['TL_LANG']['tl_iso_products']['prices_edit_button'],

        // Use a custom label for the apply button
        'applyButtonLabel' => $GLOBALS['TL_LANG']['tl_iso_products']['prices_apply_button'],

        // Order records by a particular field
        'orderField' => 'name DESC',

        // Use the callback to generate the list
        'listCallback' => array('Isotope\tl_iso_prices', 'generateWizardList'),
    ),
),

// Example list callback:
/**
 * Generate a list of prices for a wizard in products
 * @param object
 * @param string
 * @return string
 */
public function generateWizardList($objRecords, $strId)
{
    $strReturn = '';

    while ($objRecords->next()) {
        $strReturn .= '<li>' . $objRecords->name . ' (ID: ' . $objRecords->id . ')' . '</li>';
    }

    return '<ul id="sort_' . $strId . '">' . $strReturn . '</ul>';
}
```
