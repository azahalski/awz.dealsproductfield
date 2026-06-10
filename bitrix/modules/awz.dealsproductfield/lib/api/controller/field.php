<?php

namespace Awz\Dealsproductfield\Api\Controller;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Loader;
use Bitrix\Main\Error;
use Bitrix\Main\Localization\Loc;

Loc::loadMessages(__FILE__);

/**
 * Контроллер API для работы с пользовательским полем "Товары сделок"
 */
class Field extends Controller
{
    /**
     * Конфигурация действий контроллера
     *
     * @return array
     */
    public function configureActions(): array
    {
        return [
            'getDealProducts' => [
                'prefilters' => [
                    new \Bitrix\Main\Engine\ActionFilter\Authentication(),
                    new \Bitrix\Main\Engine\ActionFilter\Csrf(),
                ]
            ],
            'getDealInfo' => [
                'prefilters' => [
                    new \Bitrix\Main\Engine\ActionFilter\Authentication(),
                    new \Bitrix\Main\Engine\ActionFilter\Csrf(),
                ]
            ],
        ];
    }

    /**
     * Получает список товаров сделки
     *
     * @param int $dealId ID сделки
     * @return array
     */
    public function getDealProductsAction(int $dealId): array
    {
        // Проверяем наличие модуля CRM
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CRM_NOT_INSTALLED')));
            return [];
        }

        if ($dealId <= 0) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_INVALID_DEAL_ID')));
            return [];
        }

        // Проверяем права доступа к сделке
        $dealPermissions = \CCrmDeal::CheckReadPermission($dealId);
        if (!$dealPermissions) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_NO_ACCESS')));
            return [];
        }

        try {
            $products = [];

            // Получаем товары сделки
            $res = \CCrmDeal::LoadProductRows($dealId);
            
            if ($res && is_array($res)) {
                foreach ($res as $row) {
                    $products[] = [
                        'id' => (int)$row['ID'],
                        'name' => $row['PRODUCT_NAME'] ?? '',
                        'price' => isset($row['PRICE']) ? floatval($row['PRICE']) : 0,
                        'quantity' => isset($row['QUANTITY']) ? floatval($row['QUANTITY']) : 0,
                        'measureName' => $row['MEASURE_NAME'] ?? '',
                        'productId' => isset($row['PRODUCT_ID']) ? (int)$row['PRODUCT_ID'] : 0,
                    ];
                }
            }

            return $products;

        } catch (\Exception $e) {
            $this->addError(new Error($e->getMessage()));
            return [];
        }
    }

    /**
     * Получает информацию о сделке
     *
     * @param int $dealId ID сделки
     * @return array|null
     */
    public function getDealInfoAction(int $dealId): ?array
    {
        // Проверяем наличие модуля CRM
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CRM_NOT_INSTALLED')));
            return null;
        }

        if ($dealId <= 0) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_INVALID_DEAL_ID')));
            return null;
        }

        // Проверяем права доступа к сделке
        $dealPermissions = \CCrmDeal::CheckReadPermission($dealId);
        if (!$dealPermissions) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_NO_ACCESS')));
            return null;
        }

        try {
            $deal = \CCrmDeal::GetByID($dealId);
            
            if (!$deal) {
                $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_DEAL_NOT_FOUND')));
                return null;
            }

            return [
                'id' => (int)$deal['ID'],
                'title' => $deal['TITLE'] ?? '',
                'opportunity' => isset($deal['OPPORTUNITY']) ? floatval($deal['OPPORTUNITY']) : 0,
                'currency' => $deal['CURRENCY_ID'] ?? '',
                'stageId' => $deal['STAGE_ID'] ?? '',
                'companyId' => isset($deal['COMPANY_ID']) ? (int)$deal['COMPANY_ID'] : 0,
                'contactId' => isset($deal['CONTACT_ID']) ? (int)$deal['CONTACT_ID'] : 0,
            ];

        } catch (\Exception $e) {
            $this->addError(new Error($e->getMessage()));
            return null;
        }
    }

    /**
     * Получает список сделок по фильтру
     *
     * @param array $filter Фильтр для поиска сделок
     * @param int $limit Лимит записей
     * @param int $offset Смещение
     * @return array
     */
    public function getDealsAction(array $filter = [], int $limit = 50, int $offset = 0): array
    {
        // Проверяем наличие модуля CRM
        if (!Loader::includeModule('crm')) {
            $this->addError(new Error(Loc::getMessage('AWZ_DEALSPRODUCTFIELD_CRM_NOT_INSTALLED')));
            return [];
        }

        try {
            $deals = [];
            $arFilter = [];
            
            // Применяем фильтр
            if (!empty($filter)) {
                $arFilter = $filter;
            }

            $arOrder = ['ID' => 'DESC'];
            $arSelect = ['ID', 'TITLE', 'OPPORTUNITY', 'CURRENCY_ID', 'STAGE_ID'];
            $arNavStartParams = [
                'nPageSize' => $limit,
                'iNumPage' => 1,
                'iOffset' => $offset
            ];

            $res = \CCrmDeal::GetList($arOrder, $arFilter, $arSelect, $arNavStartParams);
            
            while ($deal = $res->Fetch()) {
                $deals[] = [
                    'id' => (int)$deal['ID'],
                    'title' => $deal['TITLE'] ?? '',
                    'opportunity' => isset($deal['OPPORTUNITY']) ? floatval($deal['OPPORTUNITY']) : 0,
                    'currency' => $deal['CURRENCY_ID'] ?? '',
                    'stageId' => $deal['STAGE_ID'] ?? '',
                ];
            }

            return $deals;

        } catch (\Exception $e) {
            $this->addError(new Error($e->getMessage()));
            return [];
        }
    }
}