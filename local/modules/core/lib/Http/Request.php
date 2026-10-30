<?php

namespace Core\Http;

use Bitrix\Main\Context;

final class Request
{
    public static function get(string $name, $default = null)
    {
        $request = Context::getCurrent()->getRequest();

        return $request->get($name) ?? $default;
    }

    public static function isPost(): bool
    {
        return Context::getCurrent()->getRequest()->isPost();
    }

    public static function isAjax(): bool
    {
        return Context::getCurrent()->getRequest()->isAjaxRequest();
    }
}
