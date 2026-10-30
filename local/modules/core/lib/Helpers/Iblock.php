<?php

namespace Core\Helpers;

use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Loader;

class Iblock
{
    public const CACHE_TIME = 86400;

    /**
     * Получение ID инфоблока по символьному коду
     * @param string $code
     * @return int|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getIblockIdByCode(string $code): ?int
    {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        $cache = Cache::createInstance();

        $cacheId = 'iblock_id_by_code_' . md5($code);
        $cacheDir = '/core/iblock';

        if ($cache->initCache(self::CACHE_TIME, $cacheId, $cacheDir)) {
            return $cache->getVars();
        }

        if ($cache->startDataCache()) {
            $iblock = IblockTable::getList([
                'select' => ['ID'],
                'filter' => [
                    '=CODE' => $code,
                ],
                'limit' => 1,
            ])->fetch();

            $iblockId = $iblock ? (int)$iblock['ID'] : null;

            $cache->endDataCache($iblockId);

            return $iblockId;
        }

        return null;
    }

    /**
     * Получение символьного кода инфоблока по его ID
     * @param int $iblockId
     * @return string|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getIblockCodeById(int $iblockId): ?string
    {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        $cache = Cache::createInstance();

        $cacheId = 'iblock_code_by_id_' . $iblockId;
        $cacheDir = '/core/iblock';

        if ($cache->initCache(self::CACHE_TIME, $cacheId, $cacheDir)) {
            return $cache->getVars();
        }

        if ($cache->startDataCache()) {
            $iblock = IblockTable::getList([
                'select' => ['CODE'],
                'filter' => [
                    '=ID' => $iblockId,
                ],
                'limit' => 1,
            ])->fetch();

            $iblockCode = $iblock ? (string)$iblock['CODE'] : null;

            $cache->endDataCache($iblockCode);

            return $iblockCode;
        }

        return null;
    }

    /**
     * Создание нового типа инфоблока
     * @param string $code Символьный код типа
     * @param string $name Наименование типа
     * @param string $sectionName Название разделов
     * @param string $elementName Название элементов
     * @param bool $sections Разрешить разделы
     * @param bool $inRss Использовать RSS
     * @param int $sort Индекс сортировки
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function createIblockType(
        string $code,
        string $name,
        string $sectionName = 'Раздел',
        string $elementName = 'Элемент',
        bool $sections = true,
        bool $inRss = false,
        int $sort = 500
    ): bool {
        if (!Loader::includeModule('iblock')) {
            return false;
        }

        // Проверяем, существует ли тип
        $existingType = \Bitrix\Iblock\TypeTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=ID' => $code,
            ],
            'limit' => 1,
        ])->fetch();

        if ($existingType) {
            return false;
        }

        $iblockType = new \CIBlockType();

        return (bool)$iblockType->Add([
            'ID' => $code,
            'SECTIONS' => $sections ? 'Y' : 'N',
            'IN_RSS' => $inRss ? 'Y' : 'N',
            'SORT' => $sort,
            'LANG' => [
                LANGUAGE_ID => [
                    'NAME' => $name,
                    'SECTION_NAME' => $sectionName,
                    'ELEMENT_NAME' => $elementName,
                ],
            ],
        ]);
    }

    /**
     * Создание нового инфоблока
     * @param string $typeId Символьный код типа инфоблока
     * @param string $name Название инфоблока
     * @param string $code Символьный код инфоблока
     * @param array $siteIds Массив ID сайтов, например ['s1']
     * @param string|null $apiCode Код для объектного ORM
     * @param bool $active Активность инфоблока
     * @param int $sort Сортировка
     * @return int|null
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function createIblock(
        string $typeId,
        string $name,
        string $code,
        array $siteIds,
        ?string $apiCode = null,
        bool $active = true,
        int $sort = 500
    ): ?int {
        if (!Loader::includeModule('iblock')) {
            return null;
        }

        // Проверяем существование инфоблока
        $existingIblock = IblockTable::getList([
            'select' => ['ID'],
            'filter' => [
                '=IBLOCK_TYPE_ID' => $typeId,
                '=CODE' => $code,
            ],
            'limit' => 1,
        ])->fetch();

        if ($existingIblock) {
            return null;
        }

        $iblock = new \CIBlock();

        $fields = [
            'IBLOCK_TYPE_ID' => $typeId,
            'NAME' => $name,
            'CODE' => $code,
            'ACTIVE' => $active ? 'Y' : 'N',
            'SORT' => $sort,
            'SITE_ID' => $siteIds,
        ];

        if ($apiCode !== null) {
            $fields['API_CODE'] = $apiCode;
        }

        $iblockId = $iblock->Add($fields);

        if (!$iblockId) {
            return null;
        }

        // Сбрасываем кеш получения ID и символьного кода
        Cache::createInstance()->cleanDir('/core/iblock');

        return (int)$iblockId;
    }
}