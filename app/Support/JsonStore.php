<?php

namespace App\Support;

class JsonStore
{
    /**
     * Membaca file JSON dari storage/app/dummy/.
     *
     * @return array<int, mixed>
     */
    // TODO: ganti dengan Eloquent
    public static function read(string $filename): array
    {
        $path = storage_path('app/dummy/'.$filename);

        if (! file_exists($path)) {
            return [];
        }

        $content = file_get_contents($path);

        return json_decode($content, true) ?: [];
    }

    /**
     * Menulis array ke file JSON di storage/app/dummy/.
     */
    public static function write(string $filename, array $data): void
    {
        $dir = storage_path('app/dummy');

        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents(
            $dir.'/'.$filename,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }

    /**
     * Menambah item ke array JSON yang sudah ada.
     */
    public static function append(string $filename, array $item): void
    {
        $data = static::read($filename);
        $data[] = $item;
        static::write($filename, $data);
    }
}
