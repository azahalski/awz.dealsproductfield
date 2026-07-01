<?php

namespace Awz\Dealsproductfield;

/**
 * Класс-помощник для работы с товарами
 */
class Helper
{
    /**
     * Получает ID выбранных строк товаров
     * 
     * @param array|string $selectedRows Массив выбранных строк товаров или JSON-строка
     * @return array Массив ID строк товаров
     */
    public static function getProductRowIds($selectedRows): array
    {
        $ids = [];

        if(!is_array($selectedRows)){
            return self::getProductRowIds([$selectedRows]);
        }
        
        // Обрабатываем каждый элемент
        foreach ($selectedRows as $row) {
            // Если элемент - строка, попробуем декодировать как JSON
            if (is_string($row) && !empty($row)) {
                $decoded = json_decode($row, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $row = $decoded;
                } elseif (json_last_error() === JSON_ERROR_NONE && is_numeric($decoded)) {
                    // Если JSON-строка содержит число
                    $ids[] = (int)$decoded;
                    continue;
                } else {
                    // Неверный JSON, пропускаем
                    continue;
                }
            }



            if(!is_array($row)) continue;

            // Если это массив с ключом 'selectedProductsData', обрабатываем его (новая структура с полными данными)
            if (isset($row['selectedProductsData']) && is_array($row['selectedProductsData'])) {
                foreach ($row['selectedProductsData'] as $productData) {
                    if (is_array($productData) && isset($productData['id']) && is_numeric($productData['id'])) {
                        $ids[] = (int)$productData['id'];
                    }
                }
                continue;
            }

            // Если это массив с ключом 'selectedProducts', обрабатываем его (старая структура с ID)
            if (isset($row['selectedProducts']) && is_array($row['selectedProducts'])) {
                foreach ($row['selectedProducts'] as $productId) {
                    if (is_numeric($productId)) {
                        $ids[] = (int)$productId;
                    }
                }
                continue;
            }
            
            // Если это структура сделок (ключи - ID сделок), извлекаем selectedProducts из каждой
            foreach ($row as $dealId => $dealData) {
                if (is_array($dealData)) {
                    // Новая структура с selectedProductsData
                    if (isset($dealData['selectedProductsData']) && is_array($dealData['selectedProductsData'])) {
                        foreach ($dealData['selectedProductsData'] as $productData) {
                            if (is_array($productData) && isset($productData['id']) && is_numeric($productData['id'])) {
                                $ids[] = (int)$productData['id'];
                            }
                        }
                    }
                    // Старая структура с selectedProducts
                    if (isset($dealData['selectedProducts']) && is_array($dealData['selectedProducts'])) {
                        foreach ($dealData['selectedProducts'] as $productId) {
                            if (is_numeric($productId)) {
                                $ids[] = (int)$productId;
                            }
                        }
                    }
                }
            }

        }
        
        // Убираем дубликаты
        return array_values(array_unique($ids));
    }

    /**
     * Получает полные данные о выбранных товарах (с ценой и количеством)
     * 
     * @param array|string $selectedRows Массив выбранных строк товаров или JSON-строка
     * @return array Массив данных о товарах с ценой и количеством
     */
    public static function getSelectedProductsData($selectedRows): array
    {
        $products = [];

        if(!is_array($selectedRows)){
            return self::getSelectedProductsData([$selectedRows]);
        }
        
        // Обрабатываем каждый элемент
        foreach ($selectedRows as $row) {
            // Если элемент - строка, попробуем декодировать как JSON
            if (is_string($row) && !empty($row)) {
                $decoded = json_decode($row, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $row = $decoded;
                } else {
                    continue;
                }
            }

            if(!is_array($row)) continue;

            // Если это массив с ключом 'selectedProductsData', обрабатываем его
            if (isset($row['selectedProductsData']) && is_array($row['selectedProductsData'])) {
                foreach ($row['selectedProductsData'] as $productData) {
                    if (is_array($productData) && isset($productData['id'])) {
                        $products[] = $productData;
                    }
                }
                continue;
            }

            // Если это структура сделок (ключи - ID сделок), извлекаем selectedProductsData из каждой
            foreach ($row as $dealId => $dealData) {
                if (is_array($dealData) && isset($dealData['selectedProductsData']) && is_array($dealData['selectedProductsData'])) {
                    foreach ($dealData['selectedProductsData'] as $productData) {
                        if (is_array($productData) && isset($productData['id'])) {
                            $products[] = $productData;
                        }
                    }
                }
            }
        }
        
        return $products;
    }

    /**
     * Создает MD5 хеш из массива ID строк товаров
     * Перед созданием хеша ID сортируются от меньшего к большему
     * 
     * @param array $ids Массив ID строк товаров
     * @return string MD5 хеш
     */
    public static function getProductRowIdsHash(array $ids): string
    {
        // Сортировка ID от меньшего к большему
        sort($ids);
        
        // Преобразуем массив в строку с разделителем
        $string = implode(',', $ids);
        
        // Создаем MD5 хеш
        return md5($string);
    }
}