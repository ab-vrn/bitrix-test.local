<?php

namespace Core\Helpers;

use Bitrix\Iblock\IblockTable;
use Bitrix\Iblock\SectionTable;
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

    /**
     * Получение фрашмента дерева подразделений и рядом находящихся подразделений одного уровня с родительским пользователя
     * @param int $userDepartmentId ID подразделения пользователя
     * @param string $iblockDepartmentCode Символьный код инфоблока
     * @return array
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\LoaderException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function getDepartmentEnviroment(int $userDepartmentId, string $iblockDepartmentCode) : array
    {
        $arResult = [];

        if ($userDepartmentId <= 0 || empty($iblockDepartmentCode)) {
            return $arResult;
        }

        $iblockId = self::getIblockIdByCode($iblockDepartmentCode);

        if ($iblockId <= 0) {
            return $arResult;
        }

        //  Информация о текущем подразделении пользователя
        $currentDepartment = SectionTable::getList(
            [
                'filter' => [
                    '=IBLOCK_ID' => $iblockId,
                    '=ID' => $iblockId,
                    '=ACTIVE' => 'Y'
                ],
                'select' => [
                    'ID',
                    'NAME',
                    'IBLOCK_SECTION_ID',
                    'LEFT_MARGIN',
                    'RIGHT_MARGIN',
                    'DEPTH_LEVEL'
                ]
            ]
        )->fetch();

        if (!$currentDepartment) {
            return $arResult;
        }

        $arResult = $currentDepartment;

        //  Информация по родительскому подразделению
        $parentDep = null;
        if (!empty($currentDepartment['IBLOCK_SECTION_ID'])) {
            $parentDep = SectionTable::getList(
                [
                    'filter' => [
                        '=IBLOCK_ID' => $iblockId,
                        '=ID' => $currentDepartment['IBLOCK_SECTION_ID'],
                        '=ACTIVE' => 'Y'
                    ],
                    'select' => [
                        'ID',
                        'IBLOCK_SECTION_ID',
                        'LEFT_MARGIN',
                        'RIGHT_MARGIN',
                        'DEPTH_LEVEL'
                    ]
                ]
            )->fetch();
        }

            //  Вышестоящие всех уровней
            $arFilter = [
                'LOGIC' => 'OR',
                [
                    '<LEFT_MARGIN' => $currentDepartment['LEFT_MARGIN'],
                    '>RIGHT_MARGIN' => $currentDepartment['RIGHT_MARGIN'],
                ]
            ];

            //  Есть родитель
            if ($parentDep) {
                $arFilter[] = [
                    [
                        '>LEFT_MARGIN' => $currentDepartment['LEFT_MARGIN'],
                        '<RIGHT_MARGIN' => $currentDepartment['RIGHT_MARGIN'],
                    ]
                ];
            } else {
                $arFilter[] = [
                    [
                        '>=LEFT_MARGIN' => $currentDepartment['LEFT_MARGIN'],
                        '<=RIGHT_MARGIN' => $currentDepartment['RIGHT_MARGIN'],
                    ]
                ];
            }

            $sections = SectionTable::getList(
                [
                    'order' => [
                        'LEFT_MARGIN' => 'ASC'
                    ],
                    'filter' => [
                        '=IBLOCK_ID' => $iblockId,
                        '=ACTIVE' => 'Y',
                        $arFilter
                    ],
                    'select' => [
                        'ID',
                        'IBLOCK_SECTION_ID',
                        'LEFT_MARGIN',
                        'RIGHT_MARGIN',
                        'DEPTH_LEVEL'
                    ]
                ]
            )->fetchAll();

            foreach ($sections as $section) {
                if ((int)$section['ID'] === (int)$currentDepartment['ID']) {
                    continue;
                }

                if (
                    $section['LEFT_MARGIN'] < $currentDepartment['LEFT_MARGIN'] &&
                    $section['RIGHT_MARGIN'] > $currentDepartment['RIGHT_MARGIN']
                ) {
                    $arResult[] = $section;
                } elseif (
                    $section['LEFT_MARGIN'] > $currentDepartment['LEFT_MARGIN'] &&
                    $section['RIGHT_MARGIN'] < $currentDepartment['RIGHT_MARGIN']
                ) {
                    $arResult[] = $section;
                } else {
                    $arResult[] = $section;
                }
            }

        return $arResult;
    }
}