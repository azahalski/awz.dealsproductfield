<?php

namespace Awz\Dealsproductfield;

use Bitrix\Bizproc\FieldType;

// Наследуемся от базового описания строк БП, чтобы не писать 20 обязательных методов проверки
class BizprocFieldType extends \Bitrix\Bizproc\BaseType\StringType
{
    /**
     * Обязательный метод! Возвращает типы, в которые поле может неявно конвертироваться.
     * Без него БП отсекает поле из интерфейсов параметров.
     */
    public static function conversionMap(): array
    {
        return [
                ['string'] // Разрешаем приводить наше поле к обычной строке
        ];
    }

    /**
     * Отрисовка поля в параметрах запуска
     */
    public static function renderControl(FieldType $fieldType, array $field, $value, array $controls, array $settings): string
    {
        // Вытаскиваем имя инпута, которое сгенерировал БП
        $controlName = $controls['Form'];
        $safeValue = is_array($value) ? implode(', ', $value) : (string)$value;

        ob_start();
        ?>
        <div class="awz-bp-control-wrapper" style="padding: 5px 0;">
            <input
                    type="text"
                    id="bp_field_<?=htmlspecialcharsbx($controlName)?>"
                    name="<?=htmlspecialcharsbx($controlName)?>"
                    value="<?=htmlspecialcharsbx($safeValue)?>"
                    class="bx-crm-edit-input"
                    style="width: 70%; display: inline-block; vertical-align: middle;"
            />
            <button
                    type="button"
                    class="ui-btn ui-btn-sm ui-btn-light-border"
                    onclick="AwzBpSelectDeals('<?=htmlspecialcharsbx($controlName)?>')"
                    style="margin-left: 5px; vertical-align: middle;"
            >
                Выбрать сделки/товары
            </button>
        </div>
        <script>
            if (typeof AwzBpSelectDeals === 'undefined') {
                function AwzBpSelectDeals(controlName) {
                    alert('Окно выбора для поля: ' + controlName);
                    // Сюда пишем логику вставки ID
                    document.getElementById('bp_field_' + controlName).value = 'DEAL_777';
                }
            }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Извлечение значения из $_POST/$_GET при сохранении параметров запуска
     */
    public static function extractValue(FieldType $fieldType, array $field, array $controls, array $request)
    {
        $controlName = $controls['Form'];
        return $request[$controlName] ?? null;
    }

    /**
     * Отображение в логе БП
     */
    public static function renderControlPrint(FieldType $fieldType, array $field, $value): string
    {
        return 'AWZ данные: ' . (is_array($value) ? implode(', ', $value) : (string)$value);
    }
}
