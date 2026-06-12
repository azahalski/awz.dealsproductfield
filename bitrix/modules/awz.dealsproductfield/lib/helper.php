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

            // Если это массив с ключом 'selectedProducts', обрабатываем его
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
                if (is_array($dealData) && isset($dealData['selectedProducts']) && is_array($dealData['selectedProducts'])) {
                    foreach ($dealData['selectedProducts'] as $productId) {
                        if (is_numeric($productId)) {
                            $ids[] = (int)$productId;
                        }
                    }
                }
            }

        }
        
        // Убираем дубликаты
        return array_values(array_unique($ids));
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