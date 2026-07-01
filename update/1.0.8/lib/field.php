<?php

namespace Awz\Dealsproductfield;

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;
use Bitrix\Main\UserField\Types\StringType;

Loc::loadMessages(__FILE__);

/**
 * Пользовательское поле для выбора сделок и товаров из этих сделок
 */
class Field extends StringType
{
    public const USER_TYPE_ID = 'awz_deals_product';
    public const RENDER_COMPONENT = 'bitrix:main.field.string';

    /**
     * Возвращает описание типа пользовательского поля
     *
     * @return array
     */
    public static function getDescription(): array
    {
        return [
            'USER_TYPE_ID' => self::USER_TYPE_ID,
            'CLASS_NAME'   => __CLASS__,
            'DESCRIPTION' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_UF_DESCRIPTION'),
            'BASE_TYPE' => \CUserTypeManager::BASE_TYPE_STRING,
            'SEARCHABLE' => false,
            'SORTABLE' => false,
            'FILTERABLE' => false,
            'SETTINGS' => [],
        ];
    }

    /**
     * Возвращает форму редактирования настроек поля
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function renderSettings($userField, ?array $additionalParameters = [], $varsFromForm): string
    {
        return '';
    }

    /**
     * Возвращает HTML для редактирования значения поля
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function renderEdit(array $userField, ?array $additionalParameters = []): string
    {
        if (!Loader::includeModule('crm')) {
            return '<div class="ui-alert ui-alert-danger">' . Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CRM_NOT_INSTALLED') . '</div>';
        }
        \CJSCore::Init(['ui.entity-selector', 'ui.dialogs', 'awz_deals_product_field']);

        $fieldName = $userField['FIELD_NAME'] ?? '';
        $value = $userField['VALUE'] ?? '';
        $multiple = $userField['MULTIPLE'] == 'Y';

        // Собираем все данные сделок в один объект
        $dealsData = [];
        
        // Если поле множественное, $value будет массивом
        if (is_array($value)) {
            // Собираем данные из всех строк множественного поля
            foreach ($value as $rowValue) {
                if (!empty($rowValue)) {
                    try {
                        $decoded = json_decode($rowValue, true);
                        if (is_array($decoded)) {
                            // Объединяем данные по сделкам
                            foreach ($decoded as $dealId => $dealInfo) {
                                $dealsData[$dealId] = $dealInfo;
                            }
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки декодирования
                    }
                }
            }
        } else {
            // Одиночное поле - декодируем одно значение
            if (!empty($value)) {
                try {
                    $decoded = json_decode($value, true);
                    $dealsData = is_array($decoded) ? $decoded : [];
                } catch (\Exception $e) {
                    $dealsData = [];
                }
            }
        }
        if(empty($dealsData) && $userField['ENTITY_ID'] == 'CRM_DEAL' && $userField['ENTITY_VALUE_ID']){
            $dealsData = [
                    $userField['ENTITY_VALUE_ID'] => [
                        'id'=>$userField['ENTITY_VALUE_ID'],
                        'title'=>'Текущая сделка',
                        'selectedProducts'=>[]
                    ]
            ];
        }

        // Получаем ID текущей сделки, если находимся в контексте сделки
        $currentDealId = 0;
        if (!empty($additionalParameters['ENTITY_ID']) && is_numeric($additionalParameters['ENTITY_ID'])) {
            $currentDealId = (int)$additionalParameters['ENTITY_ID'];
        }

        $dealsDataJson = htmlspecialcharsbx(json_encode($dealsData));
        $fieldNameEscaped = htmlspecialcharsbx($fieldName);
        $multipleAttr = $multiple ? 'data-multiple="Y"' : '';
        $currentDealAttr = $currentDealId > 0 ? 'data-current-deal="' . $currentDealId . '"' : '';

        $messagesJson = json_encode([
            'noDeals' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_NO_DEALS'),
            'totalProducts' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_TOTAL_PRODUCTS'),
            'noProducts' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_NO_PRODUCTS'),
            'selectedProducts' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_SELECTED_PRODUCTS'),
            'loading' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_LOADING'),
            'loadError' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_LOAD_ERROR'),
            'selectorNotAvailable' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_SELECTOR_NOT_AVAILABLE'),
            'unnamedProduct' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_UNNAMED_PRODUCT'),
            'selectProducts' => Loc::getMessage('AWZ_DEALSPRODUCTFIELD_SELECT_PRODUCTS')
        ], JSON_UNESCAPED_UNICODE);

        ob_start();
        ?>
        <div class="awz-deals-product-field-container" data-field-name="<?= $fieldNameEscaped ?>" data-messages="<?= htmlspecialcharsbx($messagesJson)?>" <?= $multipleAttr ?> <?= $currentDealAttr ?>>
            <div class="awz-deals-selector">
                <div id="awz-deals-selector-tag-<?= $fieldNameEscaped ?>"></div>
                <div class="awz-deals-selected-list"></div>
                <input type="hidden" name="<?= $fieldNameEscaped ?>-main"
                       class="awz-deals-product-value"
                       value=""
                       data-deals-data="<?= $dealsDataJson ?>">
            </div>
        </div>
        <script>
            (function() {
                BX.ready(function() {
                    if (typeof AwzDealsProductField !== 'undefined') {
                        AwzDealsProductField.initField('<?= $fieldNameEscaped ?>');
                    }
                });
            })();
        </script>
        <?php
        //print_r($additionalParameters);
        //print_r($userField);
        $html = ob_get_clean();
        return $html;
    }

    /**
     * Возвращает HTML для просмотра значения поля
     *
     * @param array $userField
     * @param array|null $additionalParameters
     * @return string
     */
    public static function renderView(array $userField, ?array $additionalParameters = []): string
    {
        if (!Loader::includeModule('crm')) {
            return Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CRM_NOT_INSTALLED');
        }

        $value = $userField['VALUE'] ?? '';
        $multiple = $userField['MULTIPLE'] == 'Y';

        // Собираем все данные сделок в один объект
        $dealsData = [];
        
        // Если поле множественное, $value будет массивом
        if (is_array($value)) {
            // Собираем данные из всех строк множественного поля
            foreach ($value as $rowValue) {
                if (!empty($rowValue)) {
                    try {
                        $decoded = json_decode($rowValue, true);
                        if (is_array($decoded)) {
                            // Объединяем данные по сделкам
                            foreach ($decoded as $dealId => $dealInfo) {
                                $dealsData[$dealId] = $dealInfo;
                            }
                        }
                    } catch (\Exception $e) {
                        // Игнорируем ошибки декодирования
                    }
                }
            }
        } else {
            // Одиночное поле - декодируем одно значение
            if (!empty($value)) {
                try {
                    $decoded = json_decode($value, true);
                    $dealsData = is_array($decoded) ? $decoded : [];
                } catch (\Exception $e) {
                    $dealsData = [];
                }
            }
        }

        if (empty($dealsData)) {
            return '';
        }

        ob_start();
        echo '<div class="awz-deals-product-view" style="min-height:20px;">';

        foreach ($dealsData as $dealId => $dealData) {
            $dealTitle = $dealData['title'] ?? 'Сделка #' . $dealId;
            echo '<div style="margin-bottom: 15px;">';
            echo '<strong style="font-size: 14px; color: #2066b0;">' . htmlspecialcharsbx($dealTitle) . '</strong>';

            // Получаем выбранные товары
            // Сначала проверяем новую структуру selectedProductsData (с полными данными о товарах)
            $selectedProducts = [];
            if (!empty($dealData['selectedProductsData']) && is_array($dealData['selectedProductsData'])) {
                // Новая структура - используем сохраненные данные о товарах
                $selectedProducts = $dealData['selectedProductsData'];
            } elseif (!empty($dealData['products']) && !empty($dealData['selectedProducts'])) {
                // Старая структура - получаем данные о товарах из products по ID из selectedProducts
                foreach ($dealData['products'] as $product) {
                    if (in_array($product['id'], $dealData['selectedProducts'])) {
                        $selectedProducts[] = $product;
                    }
                }
            }

            if (!empty($selectedProducts)) {
                echo '<table class="awz-view-products-table" style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 12px;">';
                //echo '<thead>';
                //echo '<tr style="background-color: #f5f5f5; border-bottom: 2px solid #e0e0e0;">';
                //echo '<th style="padding: 6px 8px; text-align: left; font-weight: 600; color: #333;">Товар</th>';
                //echo '<th style="padding: 6px 8px; text-align: right; font-weight: 600; color: #333;">Цена</th>';
                //echo '<th style="padding: 6px 8px; text-align: center; font-weight: 600; color: #333;">Кол-во</th>';
                //echo '<th style="padding: 6px 8px; text-align: right; font-weight: 600; color: #333;">Сумма</th>';
                //echo '</tr>';
                //echo '</thead>';
                echo '<tbody>';
                
                $totalSum = 0;
                foreach ($selectedProducts as $product) {
                    $productName = $product['name'] ?? 'Без названия';
                    $price = isset($product['price']) ? (float)$product['price'] : 0;
                    $quantity = isset($product['quantity']) ? (float)$product['quantity'] : 0;
                    $measureName = $product['measureName'] ?? '';
                    $sum = $price * $quantity;
                    $totalSum += $sum;
                    
                    echo '<tr style="border-bottom: 1px solid #e0e0e0;">';
                    echo '<td style="padding: 6px 8px; color: #333;">' . htmlspecialcharsbx($productName) . '<br>';
                    echo '' . number_format($price, 2, ',', ' ') . ' Х ';
                    echo '' .
                         ($quantity > 0 ? number_format($quantity, 2, ',', ' ') . ($measureName ? ' ' . htmlspecialcharsbx($measureName) : '') : '—') . 
                         '</td>';
                    echo '<td style="padding: 6px 8px; text-align: right; color: #333; font-weight: 500;white-space: nowrap;">' . number_format($sum, 2, ',', ' ') . '</td>';
                    echo '</tr>';
                }
                
                // Итого
                echo '<tr style="background-color: #FFFEEF; border-top: 1px solid #e0e0e0; font-weight: 600;">';
                echo '<td style="padding: 6px 8px; color: #333;">Итого:</td>';
                echo '<td style="padding: 6px 8px; text-align: right; color: #2066b0;white-space: nowrap;">' . number_format($totalSum, 2, ',', ' ') . '</td>';
                echo '</tr>';
                
                echo '</tbody>';
                echo '</table>';
            } else {
                echo '<div style="color: #999; font-style: italic; font-size: 12px; margin-top: 5px;">Товары не выбраны</div>';
            }

            echo '</div>';
        }

        echo '</div>';
        return ob_get_clean();
    }

    /**
     * Проверка значения поля
     *
     * @param array $userField
     * @param mixed $value
     * @return array
     */
    public static function checkFields(array $userField, $value): array
    {
        $error = [];

        return $error;
    }

    /**
     * Возвращает значение поля для сохранения в базу данных
     *
     * @param array $userField
     * @param mixed $value
     * @return string
     */
    public static function onBeforeSave(array $userField, $value): string
    {
        // Если поле множественное, Bitrix передает $value как массив строк
        if ($userField['MULTIPLE'] === 'Y' && is_array($value)) {
            // Очищаем пустые элементы
            $value = array_filter($value);
            // Возвращаем массив обратно Bitrix (он сам разложит его по строкам в БД)
            return $value;
        }elseif(is_array($value)){
            return end($value);
        }
        return $value ?? "";
    }

    public static function getDbColumnType(): string
    {
        $connection = \Bitrix\Main\Application::getConnection();
        $helper = $connection->getSqlHelper();
        return $helper->getColumnTypeByField(new \Bitrix\Main\ORM\Fields\TextField('x'));
    }
}
