<?php

namespace Core\Business\IblockProperty;

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

/**
 * Класс для вывода текста с описанием в виде HTML
 * Class DescriptionHTML
 * @package Core\User
 */
class DescriptionHTML extends \CIBlockPropertyHTML
{
    public static function GetUserTypeDescription(): array
    {
        return [
            'PROPERTY_TYPE' => 'S',
            'USER_TYPE' => 'DescriptionHTML',
            'DESCRIPTION' => 'Local: HTML/текст',
            'GetPropertyFieldHtml' => [__CLASS__, 'GetPropertyFieldHtml'],
        ];
    }

    public static function GetPropertyFieldHtml($arProperty, $value, $strHTMLControlName)
    {
        $strHTMLControlName['VALUE'] = htmlspecialcharsEx($strHTMLControlName['VALUE']);

        if (!is_array($value['VALUE'])) {
            $value = static::ConvertFromDB($arProperty, $value);
        }

        $ar = $value['VALUE'];

        if (mb_strtolower($ar['TYPE']) != 'text') {
            $ar['TYPE'] = 'html';
        } else {
            $ar['TYPE'] = 'text';
        }

        ob_start();
        ?>

        <table>
            <? if (($arProperty['WITH_DESCRIPTION'] == 'Y') && ('' != trim($strHTMLControlName['DESCRIPTION']))) { ?>
                <tr>
                    <td>Заголовок:</td>
                    <td><input type="text" name="<?=$strHTMLControlName['DESCRIPTION']?>" value="<?=$strHTMLControlName['DESCRIPTION']?>" size="80"></td>
                </tr>
            <? } ?>
            <?
                if ($strHTMLControlName['MODE'] == 'FORM_FILL' && \COption::GetOptionString('iblock', 'use_htmledit', 'Y') == 'Y' && Loader::includeModule('fileman')) {
                    ?>
                        <tr>
                            <input type="hidden" name="<?=$strHTMLControlName['VALUE']?>" value="">
                            <!--<td style="vertical-align: top">Описание:</td>-->
                            <td>
                                <?
                                    $text_name = preg_replace("/([^a-z0-9])/is", "_", $strHTMLControlName['VALUE'] . "[TEXT]");
                                    $text_type = preg_replace("/([^a-z0-9])/is", "_", $strHTMLControlName['VALUE'] . "[TEXT]");
                                    \CFileMan::AddHTMLEditorFrame($text_name, htmlspecialcharsbx($ar['TEXT']), $text_type, mb_strtolower($ar['TYPE']), 150, 'N', 0, '', '');
                                ?>
                            </td>
                        </tr>
                    <?
                } else {
                    ?>
                    <tr>
                        <td colspan="2" align="center">Тип описания</td>
                        <td align="left">
                            <input type="radio" name="<?=$strHTMLControlName['VALUE']?>" id="<?=$strHTMLControlName['VALUE']?>[TYPE][TEXT]" value="text" <? if ($ar['TYPE'] != 'html') echo " checked"?>>
                            <label for="<?=$strHTMLControlName['VALUE']?>[TYPE][TEXT]">Текст</label>
                            /
                            <input type="radio" name="<?=$strHTMLControlName['VALUE']?>" id="<?=$strHTMLControlName['VALUE']?>[TYPE][HTML]" value="html" <? if ($ar['TYPE'] == 'html') echo " checked"?>>
                            <label for="<?=$strHTMLControlName['VALUE']?>[TYPE][HTML]">Текст</label>
                        </td>
                    </tr>
                    <?
                }
            ?>
        </table>
        <br>
        <br>
        <?
        $return = ob_get_contents();
        ob_end_clean();
        return $return;
    }
}
