<?php

namespace Core\Tools;

class Data
{
    public static function replaceArrayParameters(array $array, array $replacements) : array
    {
        if (empty($array) || empty($replacements)) {
            return $array;
        }

        foreach ($array as $key => $value) {
            if (!array_key_exists($key, $replacements)) {
                continue;
            }

            $replacement = $replacements[$key];

            if (is_array($value) && is_array($replacement)) {
                $array[$key] = self::replaceArrayParameters($value, $replacement);
            } else {
                $array[$key] = $replacement;
            }
        }

        return $array;
    }
}