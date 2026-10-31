<?php

use Bitrix\Main\Loader;

define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);

ini_set('display_errors', '0');

$GLOBALS['CORE_SECTIONS_LISTS_AJAX_RESPONSE_SENT'] = false;

function coreSectionsListsSendJson(
    array $data,
    int $statusCode = 200
): void {
    $GLOBALS['CORE_SECTIONS_LISTS_AJAX_RESPONSE_SENT'] = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    http_response_code($statusCode);

    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo json_encode(
        $data,
        JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}

ob_start();

register_shutdown_function(static function (): void {
    if (!empty($GLOBALS['CORE_SECTIONS_LISTS_AJAX_RESPONSE_SENT'])) {
        return;
    }

    $error = error_get_last();

    if ($error !== null) {
        error_log(
            '[Core SectionsLists AJAX] PHP error: '
            . $error['message']
            . ' in '
            . $error['file']
            . ':'
            . $error['line']
        );
    }

    coreSectionsListsSendJson([
        'success' => false,
        'items' => [],
        'error' => 'AJAX-обработчик завершился с ошибкой.',
    ], 500);
});

try {
    require $_SERVER['DOCUMENT_ROOT']
        . '/bitrix/modules/main/include/prolog_before.php';

    global $USER;

    if (!$USER || !$USER->IsAuthorized()) {
        throw new RuntimeException('Необходима авторизация.');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Недопустимый метод запроса.');
    }

    if (!check_bitrix_sessid()) {
        throw new RuntimeException('Сессия истекла. Обновите страницу.');
    }

    if (!Loader::includeModule('iblock')) {
        throw new RuntimeException('Модуль iblock не установлен.');
    }

    $iblockId = (int)($_POST['iblock_id'] ?? 0);
    $query = trim((string)($_POST['query'] ?? ''));

    if ($iblockId <= 0) {
        throw new RuntimeException('Не указан инфоблок.');
    }

    if (mb_strlen($query) < 2) {
        throw new RuntimeException('Введите не менее двух символов.');
    }

    if (mb_strlen($query) > 100) {
        throw new RuntimeException('Слишком длинный поисковый запрос.');
    }

    if (\CIBlock::GetPermission($iblockId) < 'R') {
        throw new RuntimeException('Недостаточно прав для чтения инфоблока.');
    }

    $items = [];

    $dbSections = \CIBlockSection::GetList(
        [
            'SORT' => 'ASC',
            'NAME' => 'ASC',
        ],
        [
            '=IBLOCK_ID' => $iblockId,
            '=ACTIVE' => 'Y',
            '%NAME' => $query,
            'CHECK_PERMISSIONS' => 'Y',
            'MIN_PERMISSION' => 'R',
        ],
        false,
        ['ID', 'IBLOCK_ID', 'NAME'],
        ['nTopCount' => 20]
    );

    while ($section = $dbSections->Fetch()) {
        $sectionId = (int)$section['ID'];

        $path = [];

        $dbChain = \CIBlockSection::GetNavChain(
            $iblockId,
            $sectionId,
            ['ID', 'NAME']
        );

        while ($chainSection = $dbChain->Fetch()) {
            $path[] = (string)$chainSection['NAME'];
        }

        $items[] = [
            'ID' => $sectionId,
            'NAME' => (string)$section['NAME'],
            'PATH' => implode(' → ', $path),
        ];
    }

    coreSectionsListsSendJson([
        'success' => true,
        'items' => $items,
    ]);

} catch (\Throwable $exception) {
    coreSectionsListsSendJson([
        'success' => false,
        'items' => [],
        'error' => $exception->getMessage(),
    ], 400);
}