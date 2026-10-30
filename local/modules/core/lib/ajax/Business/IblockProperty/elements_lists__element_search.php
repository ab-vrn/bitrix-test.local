<?php

use Bitrix\Main\Loader;

/*
 * AJAX-поиск элементов для пользовательского свойства ElementsLists.
 * Файл: /local/modules/core/lib/ajax/Business/IblockProperty/elements_lists__element_search.php
 */

define('STOP_STATISTICS', true);
define('NO_AGENT_STATISTIC', true);
define('NO_AGENT_CHECK', true);

ini_set('display_errors', '0');

$GLOBALS['CORE_ELEMENTS_LISTS_AJAX_RESPONSE_SENT'] = false;

/**
 * Удаляет случайный вывод и отправляет JSON-ответ.
 */
function coreElementsListsSendJson(array $data, int $statusCode = 200): void
{
    $GLOBALS['CORE_ELEMENTS_LISTS_AJAX_RESPONSE_SENT'] = true;

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

/*
 * Буфер включается до подключения prolog_before.php:
 * предупреждения и HTML от Bitrix не должны попадать в тело JSON.
 */
ob_start();

/*
 * Если Bitrix завершит выполнение через die/exit или произойдёт фатальная
 * ошибка до отправки ответа, возвращаем JSON вместо HTML-страницы.
 */
register_shutdown_function(static function (): void {
    if (!empty($GLOBALS['CORE_ELEMENTS_LISTS_AJAX_RESPONSE_SENT'])) {
        return;
    }

    $error = error_get_last();

    if ($error !== null) {
        error_log(
            '[Core ElementsLists AJAX] PHP error: '
            . $error['message']
            . ' in '
            . $error['file']
            . ':'
            . $error['line']
        );
    }

    coreElementsListsSendJson([
        'success' => false,
        'items' => [],
        'error' => 'AJAX-обработчик завершился с ошибкой. Подробности находятся в PHP error log.',
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

    $dbElements = \CIBlockElement::GetList(
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
        [
            'nTopCount' => 20,
        ],
        [
            'ID',
            'NAME',
        ]
    );

    while ($element = $dbElements->Fetch()) {
        $items[] = [
            'ID' => (int)$element['ID'],
            'NAME' => (string)$element['NAME'],
        ];
    }

    coreElementsListsSendJson([
        'success' => true,
        'items' => $items,
        'error' => '',
    ]);

} catch (\Throwable $exception) {
    error_log(
        '[Core ElementsLists AJAX] '
        . get_class($exception)
        . ': '
        . $exception->getMessage()
        . ' in '
        . $exception->getFile()
        . ':'
        . $exception->getLine()
    );

    coreElementsListsSendJson([
        'success' => false,
        'items' => [],
        'error' => $exception->getMessage(),
    ], 500);
}
