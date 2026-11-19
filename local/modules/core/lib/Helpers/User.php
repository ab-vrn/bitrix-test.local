<?php


namespace Core\Helpers;


use Bitrix\Main\UserGroupTable;

class User
{
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
}