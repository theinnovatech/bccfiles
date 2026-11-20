<?php

namespace App\Support\Xlsx;

use DateTimeInterface;
use Carbon\Carbon;

/**
 * Small dependency-free .xlsx writer (Revision 10).
 *
 * Numbers are written as numeric cells and dates as Excel date serials with a date number
 * format, so exported quantities and dates open in Excel as real values (sortable, summable,
 * filterable) rather than text.
 *
 * Cell values:
 *   - int / float          -> number
 *   - string               -> text
 *   - null                 -> empty
 *   - bool                 -> TRUE / FALSE
 *   - XlsxWriter::date()   -> date (yyyy-mm-dd)
 *   - XlsxWriter::dateTime -> date-time (yyyy-mm-dd hh:mm)
 *   - XlsxWriter::text()   -> forced text (e.g. codes with leading zeros)
 */
class XlsxWriter
{
    public const STYLE_DEFAULT = 0;
    public const STYLE_HEADER = 1;
    public const STYLE_DATE = 2;
    public const STYLE_DATETIME = 3;
    public const STYLE_TITLE = 4;
    public const STYLE_WRAP = 5;
    public const STYLE_NOTICE = 6;
    public const STYLE_NUMBER = 7;
    public const STYLE_BOLD = 8;

    // Paper-form styles (office forms): thin ruled cells, 9 pt text.
    public const FORM_CELL = 9;
    public const FORM_CELL_CENTER = 10;
    public const FORM_MONEY = 11;
    public const FORM_QTY = 12;
    public const FORM_DATE = 13;
    public const FORM_HEAD = 14;
    public const FORM_TITLE = 15;
    public const FORM_CENTER = 16;
    public const FORM_CENTER_BOLD = 17;
    public const FORM_META = 18;
    public const FORM_LABEL = 19;
    public const FORM_TEXT = 20;
    public const FORM_MONEY_BOLD = 21;
    public const FORM_UNDERLINE = 22;
    public const FORM_CELL_BOLD = 23;
    public const FORM_UNDERLINE_LEFT = 24;
    public const FORM_UNDERLINE_BOLD = 25;
    public const FORM_CAPTION = 26;
    public const FORM_CENTER_WRAP = 27;
    public const FORM_TITLE_SMALL = 28;
    public const FORM_SIGN_NAME = 29;
    public const FORM_SIGN_DESIG = 30;

    /** @var list<array{name: string, rows: list<array{cells: array, style: ?int}>, widths: array<int, float>, merges: list<string>, freeze: ?int, heights: array<int, float>, landscape: bool}> */
    private array $sheets = [];

    public function addSheet(string $name): int
    {
        $name = $this->sheetName($name);
        $this->sheets[] = ['name' => $name, 'rows' => [], 'widths' => [], 'merges' => [], 'freeze' => null, 'heights' => [], 'landscape' => false];

        return array_key_last($this->sheets);
    }

    /**
     * Wrap a value (scalar or date()/text() cell) with an explicit style index.
     */
    public static function styled(mixed $value, int $style): array
    {
        if (is_array($value) && isset($value['t'])) {
            return $value + ['s' => $style];
        }

        return ['v' => $value, 's' => $style];
    }

    /**
     * Merge any rectangular range, e.g. merge(0, 'A5:A6').
     */
    public function merge(int $sheet, string $range): void
    {
        $this->sheets[$sheet]['merges'][] = $range;
    }

    public function setRowHeight(int $sheet, int $row, float $height): void
    {
        $this->sheets[$sheet]['heights'][$row] = $height;
    }

    public function setLandscape(int $sheet): void
    {
        $this->sheets[$sheet]['landscape'] = true;
    }

    /**
     * @param  list<mixed>  $cells
     */
    public function addRow(int $sheet, array $cells, ?int $style = null): int
    {
        $this->sheets[$sheet]['rows'][] = ['cells' => array_values($cells), 'style' => $style];

        return count($this->sheets[$sheet]['rows']);
    }

    /**
     * @param  array<int, float>  $widths  zero-based column index => width
     */
    public function setColumnWidths(int $sheet, array $widths): void
    {
        $this->sheets[$sheet]['widths'] = $widths;
    }

    /**
     * Merge a horizontal range on a 1-based row, e.g. mergeRow(0, 1, 6) => A1:F1.
     */
    public function mergeRow(int $sheet, int $row, int $columns): void
    {
        if ($columns > 1) {
            $this->sheets[$sheet]['merges'][] = 'A'.$row.':'.self::column($columns - 1).$row;
        }
    }

    public function freezeBelowRow(int $sheet, int $row): void
    {
        $this->sheets[$sheet]['freeze'] = $row;
    }

    public static function date(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ['t' => 'date', 'v' => $value];
    }

    public static function dateTime(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ['t' => 'datetime', 'v' => $value];
    }

    public static function text(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ['t' => 'string', 'v' => (string) $value];
    }

    public static function number(mixed $value): ?array
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ['t' => 'number', 'v' => $value + 0];
    }

    public function toString(): string
    {
        if ($this->sheets === []) {
            $this->addSheet('Sheet1');
        }

        $zip = new ZipBuilder();
        $zip->add('[Content_Types].xml', $this->contentTypes());
        $zip->add('_rels/.rels', $this->rootRels());
        $zip->add('docProps/app.xml', $this->appProps());
        $zip->add('docProps/core.xml', $this->coreProps());
        $zip->add('xl/workbook.xml', $this->workbook());
        $zip->add('xl/_rels/workbook.xml.rels', $this->workbookRels());
        $zip->add('xl/styles.xml', $this->styles());

        foreach ($this->sheets as $index => $sheet) {
            $zip->add('xl/worksheets/sheet'.($index + 1).'.xml', $this->sheetXml($sheet));
        }

        return $zip->build();
    }

    public static function column(int $index): string
    {
        $name = '';
        $index++;
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $name = chr(65 + $mod).$name;
            $index = intdiv($index - 1, 26);
        }

        return $name;
    }

    /**
     * Excel serial (1900 date system) for a date / date-time.
     */
    public static function serial(mixed $value, bool $withTime): ?float
    {
        try {
            $date = $value instanceof DateTimeInterface
                ? Carbon::instance($value)
                : Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }

        $utc = Carbon::create($date->year, $date->month, $date->day, $withTime ? $date->hour : 0, $withTime ? $date->minute : 0, $withTime ? $date->second : 0, 'UTC');
        $epoch = Carbon::create(1899, 12, 30, 0, 0, 0, 'UTC');

        $serial = ($utc->getTimestamp() - $epoch->getTimestamp()) / 86400;

        return $withTime ? round($serial, 8) : floor($serial);
    }

    private function sheetXml(array $sheet): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';

        if ($sheet['freeze']) {
            $row = $sheet['freeze'] + 1;
            $xml .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="'.$sheet['freeze'].'" topLeftCell="A'.$row.'" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
        }

        if ($sheet['widths'] !== []) {
            $xml .= '<cols>';
            foreach ($sheet['widths'] as $col => $width) {
                $n = $col + 1;
                $xml .= '<col min="'.$n.'" max="'.$n.'" width="'.number_format((float) $width, 2, '.', '').'" customWidth="1"/>';
            }
            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';
        foreach ($sheet['rows'] as $r => $row) {
            $rowNumber = $r + 1;
            $height = $sheet['heights'][$rowNumber] ?? null;
            $xml .= '<row r="'.$rowNumber.'"'.($height ? ' ht="'.number_format($height, 2, '.', '').'" customHeight="1"' : '').'>';
            foreach ($row['cells'] as $c => $value) {
                $xml .= $this->cellXml(self::column($c).$rowNumber, $value, $row['style']);
            }
            $xml .= '</row>';
        }
        $xml .= '</sheetData>';

        if ($sheet['merges'] !== []) {
            $xml .= '<mergeCells count="'.count($sheet['merges']).'">';
            foreach ($sheet['merges'] as $range) {
                $xml .= '<mergeCell ref="'.$range.'"/>';
            }
            $xml .= '</mergeCells>';
        }

        $xml .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>';
        $xml .= '<pageSetup paperSize="9" orientation="'.($sheet['landscape'] ? 'landscape' : 'portrait').'" fitToWidth="1" fitToHeight="0"/>';

        return $xml.'</worksheet>';
    }

    private function cellXml(string $ref, mixed $value, ?int $rowStyle): string
    {
        if (is_array($value) && array_key_exists('s', $value)) {
            $rowStyle = $value['s'];
            $value = isset($value['t']) ? ['t' => $value['t'], 'v' => $value['v']] : $value['v'];
            if (is_array($value) && ($value['v'] === null || $value['v'] === '')) {
                $value = null;
            }
        }

        if ($value === null || $value === '') {
            return $rowStyle ? '<c r="'.$ref.'" s="'.$rowStyle.'"/>' : '';
        }

        $type = null;
        $raw = $value;
        if (is_array($value) && isset($value['t'])) {
            $type = $value['t'];
            $raw = $value['v'];
        }

        if ($type === null) {
            $type = match (true) {
                is_bool($raw) => 'bool',
                is_int($raw), is_float($raw) => 'number',
                $raw instanceof DateTimeInterface => 'datetime',
                default => 'string',
            };
        }

        if ($type === 'date' || $type === 'datetime') {
            $serial = self::serial($raw, $type === 'datetime');
            if ($serial === null) {
                return $this->inlineString($ref, (string) $raw, $rowStyle);
            }
            $style = $rowStyle === self::FORM_DATE ? self::FORM_DATE : ($type === 'date' ? self::STYLE_DATE : self::STYLE_DATETIME);

            return '<c r="'.$ref.'" s="'.$style.'"><v>'.$serial.'</v></c>';
        }

        if ($type === 'number' && is_numeric($raw)) {
            return '<c r="'.$ref.'"'.($rowStyle ? ' s="'.$rowStyle.'"' : '').'><v>'.($raw + 0).'</v></c>';
        }

        if ($type === 'bool') {
            return '<c r="'.$ref.'" t="b"'.($rowStyle ? ' s="'.$rowStyle.'"' : '').'><v>'.($raw ? 1 : 0).'</v></c>';
        }

        return $this->inlineString($ref, (string) $raw, $rowStyle);
    }

    private function inlineString(string $ref, string $text, ?int $style): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $text) ?? '';
        $escaped = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $space = $text !== trim($text) || str_contains($text, "\n") ? ' xml:space="preserve"' : '';

        return '<c r="'.$ref.'" t="inlineStr"'.($style ? ' s="'.$style.'"' : '').'><is><t'.$space.'>'.$escaped.'</t></is></c>';
    }

    private function styles(): string
    {
        // fonts: 0 normal 11, 1 bold 11, 2 bold 14, 3 bold red, 4 normal 9, 5 bold 9, 6 italic 8 grey,
        //        7 bold 12, 8 normal 7.5, 9 bold 10
        $fonts = [
            '<font><sz val="11"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="11"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="14"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="11"/><color rgb="FFCE1126"/><name val="Calibri"/><family val="2"/></font>',
            '<font><sz val="9"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="9"/><name val="Calibri"/><family val="2"/></font>',
            '<font><i/><sz val="8"/><color rgb="FF6B7280"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="12"/><name val="Calibri"/><family val="2"/></font>',
            '<font><sz val="7.5"/><name val="Calibri"/><family val="2"/></font>',
            '<font><b/><sz val="10"/><name val="Calibri"/><family val="2"/></font>',
        ];
        // borders: 0 none, 1 thin all, 2 bottom only
        $borders = [
            '<border><left/><right/><top/><bottom/><diagonal/></border>',
            '<border><left style="thin"/><right style="thin"/><top style="thin"/><bottom style="thin"/><diagonal/></border>',
            '<border><left/><right/><top/><bottom style="thin"/><diagonal/></border>',
        ];
        $xf = fn (int $numFmt, int $font, int $fill, int $border, string $align = '') => '<xf numFmtId="'.$numFmt.'" fontId="'.$font.'" fillId="'.$fill.'" borderId="'.$border.'" xfId="0"'
            .($numFmt ? ' applyNumberFormat="1"' : '').' applyFont="1"'.($fill ? ' applyFill="1"' : '').($border ? ' applyBorder="1"' : '')
            .($align !== '' ? ' applyAlignment="1"><alignment '.$align.'/></xf>' : '/>');

        $cellXfs = [
            0 => '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>',
            1 => $xf(0, 1, 2, 1, 'wrapText="1" vertical="center"'),
            2 => $xf(164, 0, 0, 0),
            3 => $xf(165, 0, 0, 0),
            4 => $xf(0, 2, 0, 0),
            5 => $xf(0, 0, 0, 0, 'wrapText="1" vertical="top"'),
            6 => $xf(0, 3, 0, 0),
            7 => $xf(1, 0, 0, 0),
            8 => $xf(0, 1, 0, 0),
            self::FORM_CELL => $xf(0, 4, 0, 1, 'wrapText="1" vertical="top"'),
            self::FORM_CELL_CENTER => $xf(0, 4, 0, 1, 'horizontal="center" wrapText="1" vertical="top"'),
            self::FORM_MONEY => $xf(4, 4, 0, 1, 'horizontal="right" vertical="top"'),
            self::FORM_QTY => $xf(3, 4, 0, 1, 'horizontal="center" vertical="top"'),
            self::FORM_DATE => $xf(166, 4, 0, 1, 'horizontal="center" vertical="top"'),
            self::FORM_HEAD => $xf(0, 5, 0, 1, 'horizontal="center" vertical="center" wrapText="1"'),
            self::FORM_TITLE => $xf(0, 2, 0, 0, 'horizontal="center" vertical="center"'),
            self::FORM_CENTER => $xf(0, 4, 0, 0, 'horizontal="center"'),
            self::FORM_CENTER_BOLD => $xf(0, 9, 0, 0, 'horizontal="center"'),
            self::FORM_META => $xf(0, 6, 0, 0),
            self::FORM_LABEL => $xf(0, 5, 0, 0),
            self::FORM_TEXT => $xf(0, 4, 0, 0, 'wrapText="1" vertical="top"'),
            self::FORM_MONEY_BOLD => $xf(4, 5, 0, 1, 'horizontal="right"'),
            self::FORM_UNDERLINE => $xf(0, 4, 0, 2, 'horizontal="center" vertical="bottom"'),
            self::FORM_CELL_BOLD => $xf(0, 5, 0, 1, 'vertical="top"'),
            self::FORM_UNDERLINE_LEFT => $xf(0, 4, 0, 2, 'vertical="bottom"'),
            self::FORM_UNDERLINE_BOLD => $xf(0, 5, 0, 2, 'horizontal="center" vertical="bottom"'),
            self::FORM_CAPTION => $xf(0, 8, 0, 0, 'horizontal="center" vertical="top"'),
            self::FORM_CENTER_WRAP => $xf(0, 4, 0, 0, 'horizontal="center" vertical="top" wrapText="1"'),
            self::FORM_TITLE_SMALL => $xf(0, 7, 0, 0, 'horizontal="center"'),
            self::FORM_SIGN_NAME => $xf(0, 5, 0, 1, 'horizontal="center" vertical="bottom"'),
            self::FORM_SIGN_DESIG => $xf(0, 4, 0, 1, 'horizontal="center" vertical="top"'),
        ];
        ksort($cellXfs);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="3"><numFmt numFmtId="164" formatCode="yyyy-mm-dd"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd hh:mm"/><numFmt numFmtId="166" formatCode="mm/dd/yyyy"/></numFmts>'
            .'<fonts count="'.count($fonts).'">'.implode('', $fonts).'</fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDCE4F2"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="'.count($borders).'">'.implode('', $borders).'</borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="'.count($cellXfs).'">'.implode('', $cellXfs).'</cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }

    private function workbook(): string
    {
        $sheets = '';
        foreach ($this->sheets as $index => $sheet) {
            $sheets .= '<sheet name="'.htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8').'" sheetId="'.($index + 1).'" r:id="rId'.($index + 1).'"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheets.'</sheets></workbook>';
    }

    private function workbookRels(): string
    {
        $rels = '';
        foreach ($this->sheets as $index => $sheet) {
            $rels .= '<Relationship Id="rId'.($index + 1).'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.($index + 1).'.xml"/>';
        }
        $styleId = count($this->sheets) + 1;
        $rels .= '<Relationship Id="rId'.$styleId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$rels.'</Relationships>';
    }

    private function contentTypes(): string
    {
        $overrides = '';
        foreach ($this->sheets as $index => $sheet) {
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.($index + 1).'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .$overrides
            .'</Types>';
    }

    private function rootRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function appProps(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties" xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">'
            .'<Application>OBIMS</Application></Properties>';
    }

    private function coreProps(): string
    {
        $now = gmdate('Y-m-d\TH:i:s\Z');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:dcmitype="http://purl.org/dc/dcmitype/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            .'<dc:creator>OBIMS</dc:creator>'
            .'<dcterms:created xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:created>'
            .'<dcterms:modified xsi:type="dcterms:W3CDTF">'.$now.'</dcterms:modified>'
            .'</cp:coreProperties>';
    }

    private function sheetName(string $name): string
    {
        $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name) ?? 'Sheet';
        $name = trim(mb_substr($name, 0, 31));

        return $name === '' ? 'Sheet'.(count($this->sheets) + 1) : $name;
    }
}
