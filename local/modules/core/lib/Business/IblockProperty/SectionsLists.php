<?php

namespace Core\Business\IblockProperty;

use Bitrix\Main\Loader;

class SectionsLists
{
    public const USER_TYPE = 'GListCustom';

    /**
     * Описание пользовательского типа свойства.
     */
    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'G',
            'USER_TYPE' => self::USER_TYPE,
            'DESCRIPTION' => 'Local: Привязка к разделам в виде списка',

            'PrepareSettings' => [__CLASS__, 'PrepareSettings'],
            'GetSettingsHTML' => [__CLASS__, 'GetSettingsHTML'],

            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [__CLASS__, 'GetPropertyFieldHtmlMulty'],
        ];
    }

    /**
     * Подготовка собственных настроек свойства.
     *
     * LINK_IBLOCK_ID может находиться:
     * - непосредственно в $arProperty;
     * - в USER_TYPE_SETTINGS.
     */
    public static function PrepareSettings($arProperty): array
    {
        $userSettings = $arProperty['USER_TYPE_SETTINGS'] ?? [];

        $iblockId = (int)($arProperty['LINK_IBLOCK_ID'] ?? 0);

        if ($iblockId <= 0) {
            $iblockId = (int)($userSettings['LINK_IBLOCK_ID'] ?? 0);
        }

        return [
            'LINK_IBLOCK_ID' => $iblockId,
            'DEFAULT_VALUE' => (string)($userSettings['DEFAULT_VALUE'] ?? ''),
            'UI_FILTER_TYPE' => (int)($userSettings['UI_FILTER_TYPE'] ?? 0),
            'UI_EDIT_TYPE' => (int)($userSettings['UI_EDIT_TYPE'] ?? 1),
        ];
    }

    /**
     * HTML собственных настроек свойства.
     */
    public static function GetSettingsHTML(
        $arProperty,
        $strHTMLControlName,
        &$arPropertyFields
    ): string {
        $settings = self::PrepareSettings($arProperty);

        $name = htmlspecialcharsbx($strHTMLControlName['NAME']);

        $html = '
            <tr valign="top">
                <td>Инфоблок для выбора разделов:</td>
                <td>
                    <select name="' . $name . '[LINK_IBLOCK_ID]">
                        <option value="0">Не выбран</option>
        ';

        if (Loader::includeModule('iblock')) {
            $dbIblocks = \CIBlock::GetList(
                [
                    'SORT' => 'ASC',
                    'NAME' => 'ASC',
                ],
                [
                    'ACTIVE' => 'Y',
                ]
            );

            while ($iblock = $dbIblocks->Fetch()) {
                $iblockId = (int)$iblock['ID'];

                $selected = $iblockId === $settings['LINK_IBLOCK_ID']
                    ? ' selected'
                    : '';

                $html .= '
                    <option value="' . $iblockId . '"' . $selected . '>
                        ' . htmlspecialcharsbx($iblock['NAME']) . '
                    </option>
                ';
            }
        }

        $html .= '
                    </select>
                </td>
            </tr>

            <tr valign="top">
                <td>Значение по умолчанию (XML_ID):</td>
                <td>
                    <input
                        type="text"
                        name="' . $name . '[DEFAULT_VALUE]"
                        value="' . htmlspecialcharsbx($settings['DEFAULT_VALUE']) . '"
                    >
                </td>
            </tr>

            <tr valign="top">
                <td>Тип отображения в фильтре:</td>
                <td>
                    <select name="' . $name . '[UI_FILTER_TYPE]">
                        <option value="0" ' . ($settings['UI_FILTER_TYPE'] === 0 ? 'selected' : '') . '>
                            В виде списка
                        </option>
                        <option value="1" ' . ($settings['UI_FILTER_TYPE'] === 1 ? 'selected' : '') . '>
                            В виде карточки с плитками
                        </option>
                    </select>
                </td>
            </tr>

            <tr valign="top">
                <td>Тип отображения при редактировании:</td>
                <td>
                    <select name="' . $name . '[UI_EDIT_TYPE]">
                        <option value="0" ' . ($settings['UI_EDIT_TYPE'] === 0 ? 'selected' : '') . '>
                            В виде списка
                        </option>
                        <option value="1" ' . ($settings['UI_EDIT_TYPE'] === 1 ? 'selected' : '') . '>
                            В виде автокомплитера
                        </option>
                    </select>
                </td>
            </tr>
        ';

        return $html;
    }

    /**
     * Редактирование одиночного свойства.
     */
    public static function GetPropertyFieldHtml(
        $arProperty,
        $value,
        $strHTMLControlName
    ): string {
        return self::getFieldHtml(
            $arProperty,
            [$value],
            $strHTMLControlName,
            false
        );
    }

    /**
     * Редактирование множественного свойства.
     */
    public static function GetPropertyFieldHtmlMulty(
        $arProperty,
        $values,
        $strHTMLControlName
    ): string {
        return self::getFieldHtml(
            $arProperty,
            $values,
            $strHTMLControlName,
            true
        );
    }

    /**
     * Формирование HTML поля выбора разделов.
     */
    private static function getFieldHtml(
        $arProperty,
        $values,
        $strHTMLControlName,
        bool $multiple
    ): string {
        if (!Loader::includeModule('iblock')) {
            return '<span>Модуль iblock не установлен.</span>';
        }

        $settings = self::PrepareSettings($arProperty);
        $iblockId = $settings['LINK_IBLOCK_ID'];

        if ($iblockId <= 0) {
            return '<span>Не выбран инфоблок для привязки.</span>';
        }

        $fieldName = (string)($strHTMLControlName['VALUE'] ?? '');

        if ($fieldName === '') {
            return '';
        }

        $values = (array)$values;

        $selectedSections = [];
        $selectedIds = [];
        $nextIndex = 0;

        foreach ($values as $key => $value) {
            $sectionId = (int)($value['VALUE'] ?? 0);

            if ($sectionId <= 0) {
                continue;
            }

            $selectedIds[] = $sectionId;

            if (is_numeric($key)) {
                $nextIndex++;
            }
        }

        $selectedIds = array_values(array_unique($selectedIds));

        /*
         * Получаем выбранные разделы.
         */
        if (!empty($selectedIds)) {
            $dbSections = \CIBlockSection::GetList(
                [],
                [
                    '=IBLOCK_ID' => $iblockId,
                    '@ID' => $selectedIds,
                ],
                false,
                ['ID', 'NAME']
            );

            while ($section = $dbSections->Fetch()) {
                $sectionId = (int)$section['ID'];

                $selectedSections[$sectionId] = [
                    'ID' => $sectionId,
                    'NAME' => (string)$section['NAME'],
                    'PATH' => self::getSectionPath($iblockId, $sectionId),
                ];
            }
        }

        $uid = 'core-section-' . md5(
                $fieldName . '-' . uniqid('', true)
            );

        $selectedHtml = '';

        foreach ($values as $key => $value) {
            $sectionId = (int)($value['VALUE'] ?? 0);

            if (
                $sectionId <= 0
                || !isset($selectedSections[$sectionId])
            ) {
                continue;
            }

            $selectedHtml .= self::getSelectedTagHtml(
                $fieldName,
                (string)$key,
                $selectedSections[$sectionId],
                $multiple
            );
        }

        $ajaxUrl = '/local/modules/core/lib/ajax/Business/IblockProperty/sections_lists__section_search.php';

        return '
            <div
                class="core-section-autocomplete"
                id="' . htmlspecialcharsbx($uid) . '"
                data-iblock-id="' . $iblockId . '"
                data-ajax-url="' . htmlspecialcharsbx($ajaxUrl) . '"
                data-input-name="' . htmlspecialcharsbx($fieldName) . '"
                data-multiple="' . ($multiple ? 'Y' : 'N') . '"
                data-next-index="' . $nextIndex . '"
            >
                <div class="core-section-autocomplete__selected">
                    ' . $selectedHtml . '
                </div>

                <div class="core-section-autocomplete__input-wrapper">
                    <input
                        type="text"
                        class="core-section-autocomplete__input"
                        placeholder="Начните вводить название раздела"
                        autocomplete="off"
                    >

                    <div class="core-section-autocomplete__results"></div>
                </div>

                <div class="core-section-autocomplete__message"></div>
            </div>

            <style>
                #' . htmlspecialcharsbx($uid) . ' {
                    max-width: 600px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__selected {
                    display: flex;
                    flex-direction: column;
                    gap: 5px;
                    margin-bottom: 6px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__tag {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    gap: 10px;
                    padding: 6px 9px;
                    background: #eaf1f7;
                    border: 1px solid #cbd8e3;
                    border-radius: 3px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__tag-content {
                    min-width: 0;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__tag-name {
                    display: block;
                    font-weight: 600;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__tag-path {
                    display: block;
                    margin-top: 2px;
                    color: #667788;
                    font-size: 11px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__remove {
                    flex-shrink: 0;
                    border: 0;
                    padding: 0 3px;
                    background: transparent;
                    cursor: pointer;
                    font-size: 18px;
                    color: #777;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__remove:hover {
                    color: #d00;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__input-wrapper {
                    position: relative;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__input {
                    box-sizing: border-box;
                    width: 100%;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__results {
                    display: none;
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    z-index: 1000;
                    max-height: 280px;
                    overflow-y: auto;
                    background: #fff;
                    border: 1px solid #c6cdd3;
                    box-shadow: 0 2px 6px rgba(0,0,0,.15);
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__option {
                    padding: 8px 10px;
                    cursor: pointer;
                    border-bottom: 1px solid #eee;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__option:hover {
                    background: #f3f5f7;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__option-name {
                    display: block;
                    font-weight: 600;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__option-path {
                    display: block;
                    margin-top: 2px;
                    color: #667788;
                    font-size: 11px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__empty {
                    padding: 8px 10px;
                    color: #777;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-section-autocomplete__message {
                    display: none;
                    margin-top: 4px;
                    color: #d00;
                }
            </style>

            <script>
            (function() {
                var container = document.getElementById(' . \CUtil::PhpToJSObject($uid) . ');

                if (!container || container.dataset.initialized === "Y") {
                    return;
                }

                container.dataset.initialized = "Y";

                var input = container.querySelector(".core-section-autocomplete__input");
                var selected = container.querySelector(".core-section-autocomplete__selected");
                var results = container.querySelector(".core-section-autocomplete__results");
                var message = container.querySelector(".core-section-autocomplete__message");

                var multiple = container.dataset.multiple === "Y";

                var timer = null;
                var requestController = null;

                function hideResults() {
                    results.style.display = "none";
                    results.innerHTML = "";
                }

                function showMessage(text) {
                    message.textContent = text;
                    message.style.display = text ? "block" : "none";
                }

                function abortRequest() {
                    if (requestController) {
                        requestController.abort();
                        requestController = null;
                    }
                }

                function getSelectedIds() {
                    return Array.from(
                        selected.querySelectorAll(".core-section-autocomplete__value")
                    ).map(function(element) {
                        return String(element.value);
                    });
                }

                function createTag(item) {
                    var tag = document.createElement("div");
                    tag.className = "core-section-autocomplete__tag";
                    tag.dataset.id = String(item.ID);

                    var content = document.createElement("div");
                    content.className = "core-section-autocomplete__tag-content";

                    var name = document.createElement("span");
                    name.className = "core-section-autocomplete__tag-name";
                    name.textContent = item.NAME;

                    var path = document.createElement("span");
                    path.className = "core-section-autocomplete__tag-path";
                    path.textContent = item.PATH || "";

                    var remove = document.createElement("button");
                    remove.type = "button";
                    remove.className = "core-section-autocomplete__remove";
                    remove.textContent = "×";
                    remove.title = "Удалить";

                    var hidden = document.createElement("input");
                    hidden.type = "hidden";
                    hidden.className = "core-section-autocomplete__value";

                    if (multiple) {
                        var index = parseInt(container.dataset.nextIndex || "0", 10);

                        hidden.name = container.dataset.inputName + "[n" + index + "]";
                        container.dataset.nextIndex = String(index + 1);
                    } else {
                        hidden.name = container.dataset.inputName;
                    }

                    hidden.value = item.ID;

                    content.appendChild(name);
                    content.appendChild(path);

                    tag.appendChild(content);
                    tag.appendChild(remove);
                    tag.appendChild(hidden);

                    return tag;
                }

                function selectSection(item) {
                    abortRequest();

                    if (multiple) {
                        var ids = getSelectedIds();

                        if (ids.indexOf(String(item.ID)) !== -1) {
                            input.value = "";
                            hideResults();
                            return;
                        }
                    } else {
                        selected.innerHTML = "";
                    }

                    selected.appendChild(createTag(item));

                    input.value = "";
                    hideResults();
                    showMessage("");
                }

                function renderResults(items) {
                    results.innerHTML = "";

                    if (!items.length) {
                        var empty = document.createElement("div");
                        empty.className = "core-section-autocomplete__empty";
                        empty.textContent = "Ничего не найдено";

                        results.appendChild(empty);
                        results.style.display = "block";
                        return;
                    }

                    var selectedIds = getSelectedIds();

                    items.forEach(function(item) {
                        if (
                            multiple
                            && selectedIds.indexOf(String(item.ID)) !== -1
                        ) {
                            return;
                        }

                        var option = document.createElement("div");
                        option.className = "core-section-autocomplete__option";

                        var name = document.createElement("span");
                        name.className = "core-section-autocomplete__option-name";
                        name.textContent = item.NAME;

                        var path = document.createElement("span");
                        path.className = "core-section-autocomplete__option-path";
                        path.textContent = item.PATH || "";

                        option.appendChild(name);
                        option.appendChild(path);

                        option.addEventListener("mousedown", function(event) {
                            event.preventDefault();
                            selectSection(item);
                        });

                        results.appendChild(option);
                    });

                    if (!results.children.length) {
                        var empty = document.createElement("div");
                        empty.className = "core-section-autocomplete__empty";
                        empty.textContent = "Все найденные разделы уже выбраны";

                        results.appendChild(empty);
                    }

                    results.style.display = "block";
                }

                function search(query) {
                    abortRequest();

                    requestController = new AbortController();

                    var currentController = requestController;
                    var formData = new FormData();

                    formData.append("sessid", BX.bitrix_sessid());
                    formData.append("iblock_id", container.dataset.iblockId);
                    formData.append("query", query);

                    fetch(container.dataset.ajaxUrl, {
                        method: "POST",
                        body: formData,
                        credentials: "same-origin",
                        signal: currentController.signal
                    })
                    .then(function(response) {
                        return response.text().then(function(responseText) {
                            var data;

                            try {
                                data = JSON.parse(responseText);
                            } catch (error) {
                                throw new Error("Сервер вернул некорректный ответ.");
                            }

                            if (!response.ok || !data.success) {
                                throw new Error(
                                    data.error || "Ошибка поиска разделов."
                                );
                            }

                            return data;
                        });
                    })
                    .then(function(data) {
                        if (currentController !== requestController) {
                            return;
                        }

                        renderResults(data.items || []);
                    })
                    .catch(function(error) {
                        if (error.name === "AbortError") {
                            return;
                        }

                        hideResults();
                        showMessage(
                            error.message || "Не удалось выполнить поиск."
                        );
                    });
                }

                input.addEventListener("input", function() {
                    clearTimeout(timer);
                    abortRequest();
                    showMessage("");

                    var query = input.value.trim();

                    if (query.length < 2) {
                        hideResults();
                        return;
                    }

                    timer = setTimeout(function() {
                        search(query);
                    }, 300);
                });

                input.addEventListener("keydown", function(event) {
                    if (event.key === "Escape") {
                        hideResults();
                    }
                });

                input.addEventListener("blur", function() {
                    setTimeout(hideResults, 150);
                });

                selected.addEventListener("click", function(event) {
                    var removeButton = event.target.closest(
                        ".core-section-autocomplete__remove"
                    );

                    if (removeButton) {
                        var tag = removeButton.closest(
                            ".core-section-autocomplete__tag"
                        );

                        if (tag) {
                            tag.remove();
                        }
                    }
                });
            })();
            </script>
        ';
    }

    /**
     * HTML выбранного раздела.
     */
    private static function getSelectedTagHtml(
        string $fieldName,
        string $key,
        array $section,
        bool $multiple
    ): string {
        $inputName = $multiple
            ? $fieldName . '[' . $key . ']'
            : $fieldName;

        return '
            <div
                class="core-section-autocomplete__tag"
                data-id="' . (int)$section['ID'] . '"
            >
                <div class="core-section-autocomplete__tag-content">
                    <span class="core-section-autocomplete__tag-name">'
            . htmlspecialcharsbx($section['NAME']) .
            '</span>

                    <span class="core-section-autocomplete__tag-path">'
            . htmlspecialcharsbx($section['PATH']) .
            '</span>
                </div>

                <button
                    type="button"
                    class="core-section-autocomplete__remove"
                    title="Удалить"
                >×</button>

                <input
                    type="hidden"
                    class="core-section-autocomplete__value"
                    name="' . htmlspecialcharsbx($inputName) . '"
                    value="' . (int)$section['ID'] . '"
                >
            </div>
        ';
    }

    /**
     * Получение полного пути раздела.
     */
    private static function getSectionPath(
        int $iblockId,
        int $sectionId
    ): string {
        $path = [];

        $dbChain = \CIBlockSection::GetNavChain(
            $iblockId,
            $sectionId,
            ['ID', 'NAME']
        );

        while ($section = $dbChain->Fetch()) {
            $path[] = (string)$section['NAME'];
        }

        return implode(' → ', $path);
    }
}
