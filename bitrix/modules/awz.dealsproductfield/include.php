<?php
/*empty*/

// Подключаем CSS и JS файлы
\CJSCore::RegisterExt('awz_deals_product_field', [
    'js' => '/bitrix/js/awz.dealsproductfield/awz_deals_product_field.js',
    'css' => '/bitrix/css/awz.dealsproductfield/styles.css',
]);

\CJSCore::Init(['ui.entity-selector', 'ui.dialogs', 'awz_deals_product_field']);