<?php

namespace Core\Business\IblockProperty;

use Bitrix\Main\Loader;

class ElementsLists extends \CIBlockPropertyElementList
{
    public const USER_TYPE = 'EListCustom';

    /**
     * Описание пользовательского типа свойства.
     * @return array
     */
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

    /**
     * Подготовка настроек свойства.
     * @param $arProperty
     * @return array
     */
    public static function PrepareSettings($arProperty): array
    {
        $settings = parent::PrepareSettings($arProperty);

        $userSettings = $arProperty['USER_TYPE_SETTINGS'] ?? [];

        $settings['UI_FILTER_TYPE'] = (int)($userSettings['UI_FILTER_TYPE'] ?? 0);
        $settings['UI_EDIT_TYPE'] = (int)($userSettings['UI_EDIT_TYPE'] ?? 0);
        $settings['DEFAULT_VALUE'] = (string)($userSettings['DEFAULT_VALUE'] ?? '');

        return $settings;
    }

    /**
     * HTML настроек свойства
     * @param $arProperty
     * @param $strHTMLControlName
     * @param $arPropertyFields
     * @return string
     */
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
     * @param $arProperty
     * @param $value
     * @param $strHTMLControlName
     * @return string
     */
    public static function GetPropertyFieldHtml(
        $arProperty,
        $value,
        $strHTMLControlName
    ): string {
        $settings = self::PrepareSettings($arProperty);

        // Стандартный режим отображения.
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
     * @param $arProperty
     * @param $values
     * @param $strHTMLControlName
     * @return string
     */
    public static function GetPropertyFieldHtmlMulty(
        $arProperty,
        $values,
        $strHTMLControlName
    ): string {
        $settings = self::PrepareSettings($arProperty);

        // Стандартный режим отображения.
        if ($settings['UI_EDIT_TYPE'] !== 1) {
            return parent::GetPropertyFieldHtmlMulty(
                $arProperty,
                $values,
                $strHTMLControlName
            );
        }

        return self::getAutocompleteHtml(
            $arProperty,
            $values,
            $strHTMLControlName,
            true
        );
    }

    /**
     * Формирование HTML автокомплитера.
     *
     * @param array $arProperty
     * @param array $values
     * @param array $strHTMLControlName
     * @param bool $multiple
     *
     * @return string
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

        /*
         * Получаем ID выбранных элементов.
         *
         * Для множественного свойства сохраняем ключи существующих
         * значений, чтобы не потерять привязку к строкам свойства.
         */
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

        /*
         * Получаем названия выбранных элементов одним запросом.
         */
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
            /*
             * Существующие значения выводим с их ключами.
             */
            foreach ((array)$values as $key => $value) {
                $elementId = (int)($value['VALUE'] ?? 0);

                if (
                    $elementId <= 0
                    || !isset($selectedElements[$elementId])
                ) {
                    continue;
                }

                $elementName = $selectedElements[$elementId];

                $selectedHtml .= self::getSelectedTagHtml(
                    $fieldName,
                    (string)$key,
                    $elementId,
                    $elementName
                );

                $selectedCount++;
            }
        } else {
            /*
             * Для одиночного свойства используется одно скрытое поле.
             */
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
                    gap: 5px;
                    margin-bottom: 5px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__tag {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 4px 8px;
                    background: #eaf1f7;
                    border: 1px solid #cbd8e3;
                    border-radius: 3px;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__remove {
                    border: 0;
                    padding: 0;
                    background: transparent;
                    cursor: pointer;
                    font-size: 16px;
                    line-height: 1;
                    color: #777;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__remove:hover {
                    color: #d00;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__input-wrapper {
                    position: relative;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__input {
                    box-sizing: border-box;
                    width: 100%;
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
                    box-shadow: 0 2px 6px rgba(0,0,0,.15);
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__option {
                    padding: 8px 10px;
                    cursor: pointer;
                    border-bottom: 1px solid #eee;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__option:hover {
                    background: #f3f5f7;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__empty {
                    padding: 8px 10px;
                    color: #777;
                }

                #' . htmlspecialcharsbx($uid) . ' .core-element-autocomplete__message {
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
                        ids.push(String(element.value));
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
                        if (multiple && selectedIds.indexOf(String(item.ID)) !== -1) {
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

                    // В одиночном режиме при изменении текста
                    // прежнее значение больше не считается выбранным.
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
     * HTML одного выбранного элемента для множественного свойства
     * @param string $fieldName
     * @param string $key
     * @param int $elementId
     * @param string $elementName
     * @return string
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

    /**
     * Публичное отображение значения свойства
     * @param $arProperty
     * @param $arValue
     * @param $strHTMLControlName
     * @return mixed|string
     */
    public static function GetPublicViewHTML($arProperty, $arValue, $strHTMLControlName)
    {
        return parent::GetPublicViewHTML(
            $arProperty,
            $arValue,
            $strHTMLControlName
        );
    }

    /**
     * Получение расширенного значения
     * @param array $arProperty
     * @param array $value
     * @return array|false
     */
    public static function GetExtendedValue($arProperty, $value)
    {
        return parent::GetExtendedValue(
            $arProperty,
            $value
        );
    }

    /**
     * Настройки UI-фильтра
     * @param $arProperty
     * @param $strHTMLControlName
     * @param $fields
     */
    public static function GetUIFilterProperty($arProperty, $strHTMLControlName, &$fields)
    {
        return parent::GetUIFilterProperty(
            $arProperty,
            $strHTMLControlName,
            $fields
        );
    }

    /**
     * Административный фильтр
     * @param $arProperty
     * @param $strHTMLControlName
     * @return string
     */
    public static function GetAdminFilterHTML($arProperty, $strHTMLControlName)
    {
        return parent::GetAdminFilterHTML(
            $arProperty,
            $strHTMLControlName
        );
    }
}
