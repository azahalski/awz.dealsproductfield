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
            //return '';
        }

        ob_start();
        //print_r($dealsData);
        //print_r($userField);
        //print_r($additionalParameters);
        echo '<div class="awz-deals-product-view" style="min-height:20px;">';

        foreach ($dealsData as $dealId => $dealData) {
            $dealTitle = $dealData['title'] ?? 'Сделка #' . $dealId;
            echo '<div style="margin-bottom: 10px;">';
            echo '<strong>' . htmlspecialcharsbx($dealTitle) . '</strong><br>';

            if (!empty($dealData['products'])) {
                echo '<small>';
                $productNames = [];
                foreach ($dealData['products'] as $product) {
                    if (isset($dealData['selectedProducts']) &&
                        in_array($product['id'], $dealData['selectedProducts'])) {
                        $productNames[] = htmlspecialcharsbx($product['name']);
                    }
                }
                echo implode(', ', $productNames);
                echo '</small>';
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
