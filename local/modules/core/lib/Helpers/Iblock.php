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
    ): bool
    {
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
    ): ?int
    {
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
    public static function getDepartmentEnviroment(int $userDepartmentId, string $iblockDepartmentCode, int $depthLevel = 0): array
    {
        if ($userDepartmentId <= 0 || empty($iblockDepartmentCode)) {
            return [];
        }

        $nIblockId = self::getIblockIdByCode($iblockDepartmentCode);

        if ($nIblockId <= 0) {
            return [];
        }

        //  Информация о текущего подразделении пользователя
        $arFilterCurrentDepartment = [
            '=IBLOCK_ID' => $nIblockId,
            '=ID' => $userDepartmentId,
            '=ACTIVE' => 'Y',
        ];

        if ($depthLevel > 0) {
            $arFilterCurrentDepartment['>DEPTH_LEVEL'] = $depthLevel;
        }

        $currentDepartment = SectionTable::getRow(
            [
                'filter' => $arFilterCurrentDepartment,
                'select' => [
                    'ID',
                    'IBLOCK_SECTION_ID',
                    'LEFT_MARGIN',
                    'RIGHT_MARGIN'
                ],
                'cache' => [
                    'ttl' => 86400
                ]
            ]
        );

        if (!$currentDepartment) {
            return [];
        }

        //  Информация о родительском подразделении
        $parentDep = null;
        if (!empty($currentDepartment['IBLOCK_SECTION_ID'])) {
            $parentDep = SectionTable::getRow(
                [
                    'filter' => [
                        '=IBLOCK_ID' => $nIblockId,
                        '=ID' => (int)$currentDepartment['IBLOCK_SECTION_ID'],
                        '=ACTIVE' => 'Y'
                    ],
                    'select' => [
                        'ID',
                        'LEFT_MARGIN',
                        'RIGHT_MARGIN'
                    ],
                    'cache' => [
                        'ttl' => 86400
                    ]
                ]
            );
        }

        $arFilterSection = [
            'LIGIC' => 'OR',
            [
                '<LEFT_MARGIN' => $currentDepartment['LEFT_MARGIN'],
                '>RIGHT_MARGIN' => $currentDepartment['RIGHT_MARGIN'],
            ]
        ];

        if ($parentDep) {
            $arFilterSection[] = [
                '>LEFT_MARGIN' => $parentDep['LEFT_MARGIN'],
                '<RIGHT_MARGIN' => $parentDep['RIGHT_MARGIN'],
            ];
        } else {
            $arFilterSection[] = [
                '>=LEFT_MARGIN' => $currentDepartment['LEFT_MARGIN'],
                '=<RIGHT_MARGIN' => $currentDepartment['RIGHT_MARGIN'],
            ];
        }

        $arFilterSection = [
            '=IBLOCK_ID' => $nIblockId,
            '=ACTIVE' => 'Y',
        ];
        if ($depthLevel > 0) {
            $arFilterSection['>DEPTH_LEVEL'] = $arFilterCurrentDepartment;
        }
        // Получаем список разделов и имя родителя
        $arSections = SectionTable::getList(
            [
                'order' => [
                    'LEFT_MARGIN' => 'ASC'
                ],
                'filter' => $arFilterSection,
                'select' => [
                    'ID',
                    'NAME',
                    'SORT',
                    'IBLOCK_SECTION_ID',
                    'DEPTH_LEVEL',
                    'LEFT_MARGIN',
                    'RIGHT_MARGIN',
                    'PARENT_NAME' => 'PARENT_SECTION.NAME'
                ],
                'cache' => [
                    'ttl' => 86400
                ]
            ]
        )->fetchAll();

        //  Подсчет количества активных детей ждя каждого раздела
        $directChildrenMap = [];
        foreach ($arSections as $section) {
            $parentId = (int)$section['IBLOCK_SECTION_ID'];
            if ($parentId > 0) {
                $directChildrenMap[$parentId] = ($directChildrenMap[$parentId] ?? 0) + 1;
            }
        }

        foreach ($arSections as &$section) {
            $sectionId = (int)$section['ID'];

            $directChildrenCount = $directChildrenMap[$sectionId] ?? 0;

            $section['PARENT_NAME'] = ($section['DEPTH_LEVEL'] > 2) ? $section['PARENT_NAME'] : null;
            $section['HAS_CHILDREN'] = $directChildrenCount > 0;
            $section['CHILDREN_COUNT'] = $directChildrenCount;

            unset($section['PARENT_NAME']);
        }
        unser($section);

        return $arSections;
    }
}