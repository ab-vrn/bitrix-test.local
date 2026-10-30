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
}