<?php

namespace App\Support\Xlsx;

/**
 * Minimal in-memory ZIP writer (deflate when zlib is available, stored otherwise).
 * Used for .xlsx output so exports do not depend on the optional zip extension.
 */
class ZipBuilder
{
    /** @var list<array{name: string, crc: int, compressed: int, size: int, method: int, offset: int}> */
    private array $entries = [];

    private string $data = '';

    public function add(string $name, string $contents): void
    {
        $crc = crc32($contents);
        $size = strlen($contents);
        $method = 0;
        $payload = $contents;

        if (function_exists('gzdeflate')) {
            $deflated = gzdeflate($contents, 6);
            if ($deflated !== false && strlen($deflated) < $size) {
                $payload = $deflated;
                $method = 8;
            }
        }

        [$time, $date] = $this->dosTime();
        $offset = strlen($this->data);

        $this->data .= pack(
            'VvvvvvVVVvv',
            0x04034B50,
            20,
            0x0800,
            $method,
            $time,
            $date,
            $crc,
            strlen($payload),
            $size,
            strlen($name),
            0
        ).$name.$payload;

        $this->entries[] = [
            'name' => $name,
            'crc' => $crc,
            'compressed' => strlen($payload),
            'size' => $size,
            'method' => $method,
            'offset' => $offset,
            'time' => $time,
            'date' => $date,
        ];
    }

    public function build(): string
    {
        $central = '';
        foreach ($this->entries as $entry) {
            $central .= pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014B50,
                20,
                20,
                0x0800,
                $entry['method'],
                $entry['time'],
                $entry['date'],
                $entry['crc'],
                $entry['compressed'],
                $entry['size'],
                strlen($entry['name']),
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            ).$entry['name'];
        }

        $end = pack(
            'VvvvvVVv',
            0x06054B50,
            0,
            0,
            count($this->entries),
            count($this->entries),
            strlen($central),
            strlen($this->data),
            0
        );

        return $this->data.$central.$end;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function dosTime(): array
    {
        $now = getdate();
        $time = ($now['hours'] << 11) | ($now['minutes'] << 5) | intdiv($now['seconds'], 2);
        $date = (max(0, $now['year'] - 1980) << 9) | ($now['mon'] << 5) | $now['mday'];

        return [$time, $date];
    }
}
