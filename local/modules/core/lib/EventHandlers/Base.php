<?php

namespace Core\EventHandlers;

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

class Base
{
    /**
     * Регистрация всех обработчиков событий модуля.
     */
    public static function register(): void
    {
        self::registerIBlock();
    }

    /**
     * Регистрация обработчиков модуля iblock.
     */
    private static function registerIBlock(): void
    {
        if (!Loader::includeModule('iblock')) {
            return;
        }

        $eventManager = EventManager::getInstance();

        /**
         * Пользовательский тип свойства "HTML/текст".
         */
        $eventManager->addEventHandler('iblock', 'OnIBlockPropertyBuildList', [\Core\Business\IblockProperty\DescriptionHTML::class, 'GetUserTypeDescription']);
        /**
         * Пользовательский тип свойства "Local: Привязка к элементам в виде списка"
         */
        $eventManager->addEventHandler('iblock', 'OnIBlockPropertyBuildList', [\Core\Business\IblockProperty\ElementsLists::class, 'GetUserTypeDescription']);
    }
}