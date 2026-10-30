<?php

namespace Core\Cache;

use Bitrix\Main\Data\Cache as BitrixCache;

final class Cache
{
    /**
     * Получить данные из кеша или выполнить callback и сохранить результат.
     *
     * @param string $key Уникальный ключ кеша
     * @param int $ttl Время жизни в секундах
     * @param callable $callback Функция получения данных
     * @param string $dir Директория кеша
     * @return mixed
     */
    public static function remember(
        string $key,
        int $ttl,
        callable $callback,
        string $dir = '/'
    ) {
        $cache = BitrixCache::createInstance();

        if ($cache->initCache($ttl, $key, $dir)) {
            return $cache->getVars();
        }

        if ($cache->startDataCache()) {
            try {
                $result = $callback();
                $cache->endDataCache($result);

                return $result;
            } catch (\Throwable $exception) {
                $cache->abortDataCache();
                throw $exception;
            }
        }

        return $callback();
    }
}
