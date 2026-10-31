<?php

namespace Core\Business\IblockProperty;

use Bitrix\Main\Loader;

class ElementsLists extends \CIBlockPropertyElementList
{
    public const USER_TYPE = 'EListCustom';

    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'E',
            'USER_TYPE' => self::USER_TYPE,
            'DESCRIPTION' => 'Local: Привязка к элементам в виде списка',

            'PrepareSettings' => [__CLASS__, 'PrepareSettings'],
            'GetSettingsHTML' => [__CLASS__, 'GetSettingsHTML'],

            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
            'GetPropertyFieldHtmlMulty' => [__CLASS__, 'GetPropertyFieldHtmlMulty'],

            'GetPublicViewHTML' => [__CLASS__, 'GetPublicViewHTML'],
            'GetExtendedValue' => [__CLASS__, 'GetExtendedValue'],

            'GetUIFilterProperty' => [__CLASS__, 'GetUIFilterProperty'],
            'GetAdminFilterHTML' => [__CLASS__, 'GetAdminFilterHTML'],
        ];
    }

    public static function PrepareSettings($arProperty): array
    {
        $settings = parent::PrepareSettings($arProperty);

        $userSettings = $arProperty['USER_TYPE_SETTINGS'] ?? [];

        $settings['UI_FILTER_TYPE'] = (int)($userSettings['UI_FILTER_TYPE'] ?? 0);
        $settings['UI_EDIT_TYPE'] = (int)($userSettings['UI_EDIT_TYPE'] ?? 0);
        $settings['DEFAULT_VALUE'] = (string)($userSettings['DEFAULT_VALUE'] ?? '');

        return $settings;
    }

    public static function GetSettingsHTML(
        $arProperty,
        $strHTMLControlName,
        &$arPropertyFields
    ): string {
        $html = parent::GetSettingsHTML(
            $arProperty,
            $strHTMLControlName,
            $arPropertyFields
        );

        $settings = self::PrepareSettings($arProperty);
        $name = htmlspecialcharsbx($strHTMLControlName['NAME']);

        $html .= '
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

            <tr valign="center">
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

            <tr valign="center">
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
        $settings = self::PrepareSettings($arProperty);

        if ($settings['UI_EDIT_TYPE'] !== 1) {
            return parent::GetPropertyFieldHtml(
                $arProperty,
                $value,
                $strHTMLControlName
            );
        }

        return self::getAutocompleteHtml(
            $arProperty,
            [$value],
            $strHTMLControlName,
            false
        );
    }

    /**
     * Редактирование множественного свойства.
     *
     * Для множественного свойства всегда используем собственный
     * интерфейс с тегами, независимо от UI_EDIT_TYPE.
     */
    public static function GetPropertyFieldHtmlMulty(
        $arProperty,
        $values,
        $strHTMLControlName
    ): string {
        return self::getAutocompleteHtml(
            $arProperty,
            $values,
            $strHTMLControlName,
            true
        );
    }

    /**
     * Формирование HTML автокомплитера.
     */
    private static function getAutocompleteHtml(
        $arProperty,
        $values,
        $strHTMLControlName,
        bool $multiple = false
    ): string {
        if (!Loader::includeModule('iblock')) {
            return '';
        }

        $iblockId = (int)($arProperty['LINK_IBLOCK_ID'] ?? 0);

        if ($iblockId <= 0) {
            return '<span>Не выбран инфоблок для привязки.</span>';
        }

        $fieldName = (string)($strHTMLControlName['VALUE'] ?? '');

        if ($fieldName === '') {
            return '';
        }

        $selectedElements = [];
        $nextIndex = 0;
        $selectedIds = [];

        foreach ((array)$values as $key => $value) {
            $elementId = (int)($value['VALUE'] ?? 0);

            if ($elementId <= 0) {
                continue;
            }

            $selectedIds[] = $elementId;

            if (is_numeric($key)) {
                $nextIndex++;
            }
        }

        $selectedIds = array_values(array_unique($selectedIds));

        if (!empty($selectedIds)) {
            $dbElements = \CIBlockElement::GetList(
                [],
                [
                    '=IBLOCK_ID' => $iblockId,
                    '@ID' => $selectedIds,
                ],
                false,
                false,
                ['ID', 'NAME']
            );

            while ($element = $dbElements->Fetch()) {
                $selectedElements[(int)$element['ID']] = (string)$element['NAME'];
            }
        }

        $uid = 'core-element-' . md5(
                $fieldName . '-' . uniqid('', true)
            );

        $selectedHtml = '';
        $selectedCount = 0;

        if ($multiple) {
            foreach ((array)$values as $key => $value) {
                $elementId = (int)($value['VALUE'] ?? 0);

                if (
                    $elementId <= 0
                    || !isset($selectedElements[$elementId])
                ) {
                    continue;
                }

                $selectedHtml .= self::getSelectedTagHtml(
                    $fieldName,
                    (string)$key,
                    $elementId,
                    $selectedElements[$elementId]
                );

                $selectedCount++;
            }
        } else {
            $elementId = (int)($values[0]['VALUE'] ?? 0);
            $elementName = $selectedElements[$elementId] ?? '';

            $selectedHtml = '
                <input
                    type="hidden"
                    class="core-element-autocomplete__value"
                    name="' . htmlspecialcharsbx($fieldName) . '"
                    value="' . ($elementName !== '' ? $elementId : 0) . '"
                >
            ';
        }

        $ajaxUrl = '/local/modules/core/lib/ajax/Business/IblockProperty/elements_lists__element_search.php';

        $multipleAttribute = $multiple ? 'Y' : 'N';

        return '
            <div
                class="core-element-autocomplete"
                id="' . htmlspecialcharsbx($uid) . '"
                data-iblock-id="' . $iblockId . '"
                data-ajax-url="' . htmlspecialcharsbx($ajaxUrl) . '"
                data-input-name="' . htmlspecialcharsbx($fieldName) . '"
                data-multiple="' . $multipleAttribute . '"
                data-next-index="' . $nextIndex . '"
            >
                <div class="core-element-autocomplete__selected">
                    ' . $selectedHtml . '
                </div>

                <div class="core-element-autocomplete__input-wrapper">
                    <input
                        type="text"
                        class="core-element-autocomplete__input"
                        placeholder="Начните вводить название элемента"
                        autocomplete="off"
                    >

                    <div class="core-element-autocomplete__results"></div>
                </div>

                <div class="core-element-autocomplete__message"></div>
            </div>

            <style>
                #' . htmlspecialcharsbx($uid) . ' {
                    max-width: 500px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__selected {
                    display: flex;
                    flex-wrap: wrap;
                    gap: 6px;
                    margin-bottom: 8px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__tag {
                    display: inline-flex;
                    align-items: center;
                    gap: 9px;
                    padding: 6px 10px;
                    background: #edf4fa;
                    border: 1px solid #cbdce9;
                    border-radius: 5px;
                    color: #334b60;
                    font-size: 13px;
                    line-height: 18px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__tag-name {
                    overflow-wrap: anywhere;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__remove {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    width: 18px;
                    height: 18px;
                    padding: 0;
                    border: 0;
                    border-radius: 50%;
                    background: transparent;
                    color: #718096;
                    cursor: pointer;
                    font-size: 17px;
                    line-height: 1;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__remove:hover {
                    background: #dce8f1;
                    color: #c0392b;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__input-wrapper {
                    position: relative;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__input {
                    box-sizing: border-box;
                    width: 100%;
                    min-height: 34px;
                    padding: 6px 9px;
                    border: 1px solid #c6cdd3;
                    border-radius: 4px;
                    outline: none;
                    background: #fff;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__input:focus {
                    border-color: #6da6d4;
                    box-shadow: 0 0 0 2px rgba(70, 140, 200, .12);
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__results {
                    display: none;
                    position: absolute;
                    top: 100%;
                    left: 0;
                    right: 0;
                    z-index: 1000;
                    max-height: 250px;
                    overflow-y: auto;
                    background: #fff;
                    border: 1px solid #c6cdd3;
                    border-radius: 4px;
                    box-shadow: 0 3px 10px rgba(0, 0, 0, .12);
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__option {
                    padding: 9px 11px;
                    cursor: pointer;
                    border-bottom: 1px solid #eee;
                    color: #333;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__option:last-child {
                    border-bottom: 0;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__option:hover {
                    background: #f1f6fa;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__empty {
                    padding: 9px 11px;
                    color: #777;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__message {
                    display: none;
                    margin-top: 5px;
                    color: #c0392b;
                    font-size: 12px;
                }
            </style>

            <script>
            (function() {
                var container = document.getElementById(' . \CUtil::PhpToJSObject($uid) . ');

                if (!container || container.dataset.initialized === "Y") {
                    return;
                }

                container.dataset.initialized = "Y";

                var input = container.querySelector(".core-element-autocomplete__input");
                var selected = container.querySelector(".core-element-autocomplete__selected");
                var results = container.querySelector(".core-element-autocomplete__results");
                var message = container.querySelector(".core-element-autocomplete__message");

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
                    var ids = [];

                    selected.querySelectorAll(".core-element-autocomplete__value").forEach(function(element) {
                        if (element.value) {
                            ids.push(String(element.value));
                        }
                    });

                    return ids;
                }

                function createTag(item) {
                    var tag = document.createElement("div");

                    tag.className = "core-element-autocomplete__tag";
                    tag.dataset.id = String(item.ID);

                    var name = document.createElement("span");
                    name.className = "core-element-autocomplete__tag-name";
                    name.textContent = item.NAME;

                    var remove = document.createElement("button");
                    remove.type = "button";
                    remove.className = "core-element-autocomplete__remove";
                    remove.textContent = "×";
                    remove.title = "Удалить";

                    var hidden = document.createElement("input");
                    hidden.type = "hidden";
                    hidden.className = "core-element-autocomplete__value";

                    var index = parseInt(container.dataset.nextIndex || "0", 10);

                    hidden.name = container.dataset.inputName + "[n" + index + "]";
                    hidden.value = item.ID;

                    container.dataset.nextIndex = String(index + 1);

                    tag.appendChild(name);
                    tag.appendChild(remove);
                    tag.appendChild(hidden);

                    return tag;
                }

                function selectElement(item) {
                    abortRequest();

                    if (multiple) {
                        var ids = getSelectedIds();

                        if (ids.indexOf(String(item.ID)) !== -1) {
                            input.value = "";
                            hideResults();
                            return;
                        }

                        selected.appendChild(createTag(item));
                        input.value = "";
                    } else {
                        selected.innerHTML = "";

                        var hidden = document.createElement("input");

                        hidden.type = "hidden";
                        hidden.className = "core-element-autocomplete__value";
                        hidden.name = container.dataset.inputName;
                        hidden.value = item.ID;

                        selected.appendChild(hidden);

                        input.value = item.NAME;
                    }

                    hideResults();
                    showMessage("");
                }

                function renderResults(items) {
                    results.innerHTML = "";

                    if (!items.length) {
                        var empty = document.createElement("div");

                        empty.className = "core-element-autocomplete__empty";
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

                        option.className = "core-element-autocomplete__option";
                        option.textContent = item.NAME;

                        option.addEventListener("mousedown", function(event) {
                            event.preventDefault();
                            selectElement(item);
                        });

                        results.appendChild(option);
                    });

                    if (results.children.length === 0) {
                        var empty = document.createElement("div");

                        empty.className = "core-element-autocomplete__empty";
                        empty.textContent = "Все найденные элементы уже выбраны";

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
                    .then(function(httpResponse) {
                        return httpResponse.text().then(function(responseText) {
                            var responseData;

                            try {
                                responseData = JSON.parse(responseText);
                            } catch (parseError) {
                                console.error(
                                    "Некорректный ответ AJAX. HTTP:",
                                    httpResponse.status,
                                    "Ответ сервера:",
                                    responseText
                                );

                                throw new Error(
                                    "Сервер вернул некорректный ответ. Подробности в консоли браузера."
                                );
                            }

                            if (!httpResponse.ok || !responseData.success) {
                                throw new Error(
                                    responseData.error
                                    || ("Ошибка HTTP " + httpResponse.status)
                                );
                            }

                            return responseData;
                        });
                    })
                    .then(function(responseData) {
                        if (currentController !== requestController) {
                            return;
                        }

                        renderResults(responseData.items || []);
                    })
                    .catch(function(error) {
                        if (error.name === "AbortError") {
                            return;
                        }

                        hideResults();
                        showMessage(error.message || "Не удалось выполнить поиск");
                    });
                }

                input.addEventListener("input", function() {
                    clearTimeout(timer);
                    abortRequest();
                    showMessage("");

                    var query = input.value.trim();

                    if (!multiple) {
                        var hidden = selected.querySelector(".core-element-autocomplete__value");

                        if (hidden) {
                            hidden.value = "";
                        }
                    }

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
                    var removeButton = event.target.closest(".core-element-autocomplete__remove");

                    if (removeButton) {
                        var tag = removeButton.closest(".core-element-autocomplete__tag");

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
     * HTML одного выбранного элемента для множественного свойства.
     */
    private static function getSelectedTagHtml(
        string $fieldName,
        string $key,
        int $elementId,
        string $elementName
    ): string {
        $inputName = $fieldName . '[' . $key . ']';

        return '
            <div
                class="core-element-autocomplete__tag"
                data-id="' . $elementId . '"
            >
                <span class="core-element-autocomplete__tag-name">'
            . htmlspecialcharsbx($elementName) .
            '</span>

                <button
                    type="button"
                    class="core-element-autocomplete__remove"
                    title="Удалить"
                >×</button>

                <input
                    type="hidden"
                    class="core-element-autocomplete__value"
                    name="' . htmlspecialcharsbx($inputName) . '"
                    value="' . $elementId . '"
                >
            </div>
        ';
    }

    public static function GetPublicViewHTML($arProperty, $arValue, $strHTMLControlName)
    {
        return parent::GetPublicViewHTML(
            $arProperty,
            $arValue,
            $strHTMLControlName
        );
    }

    public static function GetExtendedValue($arProperty, $value)
    {
        return parent::GetExtendedValue(
            $arProperty,
            $value
        );
    }

    public static function GetUIFilterProperty($arProperty, $strHTMLControlName, &$fields)
    {
        return parent::GetUIFilterProperty(
            $arProperty,
            $strHTMLControlName,
            $fields
        );
    }

    public static function GetAdminFilterHTML($arProperty, $strHTMLControlName)
    {
        return parent::GetAdminFilterHTML(
            $arProperty,
            $strHTMLControlName
        );
    }
}
