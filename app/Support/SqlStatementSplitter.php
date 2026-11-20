<?php

namespace App\Support;

/**
 * Splits a MySQL / MariaDB dump into individual statements.
 *
 * Handles quoted strings (with backslash and doubled-quote escapes), back-ticked identifiers,
 * line comments (-- and #), block comments (dropped) and executable conditional comments
 * (/*! ... *\/, kept), as well as the client-side DELIMITER directive used for routines.
 */
class SqlStatementSplitter
{
    /**
     * @return \Generator<int, string>
     */
    public static function split(string $sql): \Generator
    {
        $length = strlen($sql);
        $delimiter = ';';
        $buffer = '';
        $i = 0;
        $atLineStart = true;

        while ($i < $length) {
            if ($atLineStart) {
                // DELIMITER directive (client-side, only valid at the start of a line).
                if (preg_match('/\G[ \t]*DELIMITER[ \t]+(\S+)[ \t]*(\r?\n|$)/Ai', $sql, $match, 0, $i)) {
                    if (trim($buffer) !== '') {
                        yield trim($buffer);
                        $buffer = '';
                    }
                    $delimiter = $match[1];
                    $i += strlen($match[0]);
                    continue;
                }
            }

            $char = $sql[$i];
            $next = $i + 1 < $length ? $sql[$i + 1] : '';

            // Line comments.
            if (($char === '-' && $next === '-' && ($i + 2 >= $length || ctype_space($sql[$i + 2]))) || $char === '#') {
                $end = strpos($sql, "\n", $i);
                $i = $end === false ? $length : $end + 1;
                $atLineStart = true;
                $buffer .= "\n";
                continue;
            }

            // Block comments; keep executable conditional comments /*! ... */.
            if ($char === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $length : $end + 2;
                if ($i + 2 < $length && $sql[$i + 2] === '!') {
                    $buffer .= substr($sql, $i, $end - $i);
                }
                $i = $end;
                $atLineStart = false;
                continue;
            }

            // Quoted strings / identifiers.
            if ($char === "'" || $char === '"' || $char === '`') {
                $quote = $char;
                $start = $i;
                $i++;
                while ($i < $length) {
                    $c = $sql[$i];
                    if ($c === '\\' && $quote !== '`') {
                        $i += 2;
                        continue;
                    }
                    if ($c === $quote) {
                        if ($i + 1 < $length && $sql[$i + 1] === $quote) {
                            $i += 2;
                            continue;
                        }
                        $i++;
                        break;
                    }
                    $i++;
                }
                $buffer .= substr($sql, $start, $i - $start);
                $atLineStart = false;
                continue;
            }

            // Statement delimiter.
            if ($char === $delimiter[0] && substr($sql, $i, strlen($delimiter)) === $delimiter) {
                if (trim($buffer) !== '') {
                    yield trim($buffer);
                }
                $buffer = '';
                $i += strlen($delimiter);
                $atLineStart = false;
                continue;
            }

            $buffer .= $char;
            $atLineStart = $char === "\n";
            $i++;
        }

        if (trim($buffer) !== '') {
            yield trim($buffer);
        }
    }

    /**
     * Table a statement targets (null for session statements such as SET).
     */
    public static function targetTable(string $statement): ?string
    {
        $normalized = preg_replace('/^\/\*!\d*\s*/', '', ltrim($statement)) ?? $statement;

        if (preg_match(
            '/^(?:DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+(?:IGNORE\s+)?INTO|REPLACE\s+INTO|ALTER\s+TABLE|LOCK\s+TABLES|TRUNCATE(?:\s+TABLE)?)\s+`?([A-Za-z0-9_$]+)`?/i',
            $normalized,
            $match
        )) {
            return $match[1];
        }

        return null;
    }

    public static function kind(string $statement): string
    {
        $normalized = strtoupper(preg_replace('/^\/\*!\d*\s*/', '', ltrim($statement)) ?? $statement);

        return match (true) {
            str_starts_with($normalized, 'CREATE TABLE') => 'create',
            str_starts_with($normalized, 'DROP TABLE') => 'drop',
            str_starts_with($normalized, 'INSERT') || str_starts_with($normalized, 'REPLACE') => 'insert',
            str_starts_with($normalized, 'LOCK TABLES') || str_starts_with($normalized, 'UNLOCK TABLES') => 'lock',
            str_starts_with($normalized, 'USE ') || str_starts_with($normalized, 'CREATE DATABASE') || str_starts_with($normalized, 'DROP DATABASE') => 'database',
            str_starts_with($normalized, 'START TRANSACTION') || str_starts_with($normalized, 'COMMIT') || str_starts_with($normalized, 'BEGIN') => 'transaction',
            default => 'other',
        };
    }
}
