<?php

use Bitrix\Main\ModuleManager;

class core extends CModule
{
    public $MODULE_ID = 'core';
    public $MODULE_NAME = 'Core — библиотека вспомогательных классов';
    public $MODULE_DESCRIPTION = 'Общая библиотека вспомогательных классов для проектов на 1С-Битрикс.';
    public $PARTNER_NAME = 'Project';
    public $PARTNER_URI = '';

    public function __construct()
    {
        $arModuleVersion = [];
        include __DIR__ . '/version.php';

        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
    }

    public function DoInstall(): void
    {
        ModuleManager::registerModule($this->MODULE_ID);
    }

    public function DoUninstall(): void
    {
        ModuleManager::unRegisterModule($this->MODULE_ID);
    }
}
