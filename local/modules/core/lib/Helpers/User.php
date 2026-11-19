<?php


namespace Core\Helpers;


use Bitrix\Main\UserGroupTable;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\UserTable;
use Bitrix\Main\UserUtils;

class User
{
    const CACHE_TIME = 86400;

    /**
     * Состоит ли пользователь в группе
     * @param int $userId ID пользователя
     * @param string $stringId Символьный код группы
     * @return bool
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function isUserInGroup(int $userId, string $stringId) : bool
    {
        if ($userId <= 0 || empty($stringId)) {
            return false;
        }

        $isMember = UserGroupTable::getRow(
            [
                'filter' => [
                    '=USER_ID' => $userId,
                    '=GROUP.STRING_ID' => $stringId,
                    '=GROUP.ACTIVE' => 'Y'
                ],
                'select' => [
                    'USER_ID'
                ]
            ]
        );

        if ($isMember) {
            return true;
        }

        return false;
    }

    /**
     * Поиск пользователя по ФИО
     * @param string $sFIO
     * @param array $arFilterExt
     * @return array|false
     * @throws \Bitrix\Main\ArgumentException
     * @throws \Bitrix\Main\ObjectPropertyException
     * @throws \Bitrix\Main\SystemException
     */
    public static function searchByFIO(string $sFIO, array $arFilterExt = [])
    {
        $arResult = [];

        if (!empty($sFIO)) {
            $obCache = Cache::createInstance();
            if ($obCache->initCache(
                self::CACHE_TIME,
                $sFIO
                . ' | ' . md5(json_encode($arFilterExt))
                . ' |Core\Helpers\User::searchByFIO#001'
            )) {
                $arResult = $obCache->getVars();
            } elseif ($obCache->startDataCache()) {
                $arFilterDefault = [];

                $arFilterFIO = UserUtils::getUserSearchFilter(
                    [
                        'FIND' => $sFIO
                    ]
                );

                $arUser = UserTable::getList(
                    [
                        'filter' => array_merge($arFilterDefault, $arFilterFIO, $arFilterExt),
                        'select' => [
                            'ID',
                            'LAST_NAME',
                            'NAME',
                            'SECOND_NAME',
                            'EMAIL'
                        ]
                    ]
                )->fetch();

                if (!empty($arUser)) {
                    $arResult = $arUser;
                }

                $obCache->endDataCache($arResult);
            }
        }

        return $arResult;
    }
}