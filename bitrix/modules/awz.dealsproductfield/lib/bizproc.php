<?php
namespace Awz\Dealsproductfield;

class Bizproc
{
    public static function onUserTypeToBizprocType(\Bitrix\Main\Event $event)
    {
        $arFields = $event->getParameters();

        if ($arFields['USER_TYPE_ID'] === 'awz_deals_product') {
            return [
                'TYPE' => 'awz_deals_product_bp'
            ];
        }
    }

    public static function onGetFieldTypes(\Bitrix\Main\Event $event)
    {
        return [
            'awz_deals_product_bp' => [
                'Name' => 'Выбор товаров сделок',
                'BaseType' => 'string', // Данные в БД БП будут храниться как строка
                'Class' => '\Awz\Dealsproductfield\BizprocFieldType',
                'IsAutoConvertible' => true,
                'RenderControl' => ['\Awz\Dealsproductfield\BizprocFieldType', 'renderControl'],
                'ExtractValue' => ['\Awz\Dealsproductfield\BizprocFieldType', 'extractValue'],
                'RenderControlPrint' => ['\Awz\Dealsproductfield\BizprocFieldType', 'renderControlPrint'],
            ]
        ];
    }
}