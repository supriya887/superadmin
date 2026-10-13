<?php

namespace App\Support;

/**
 * Dependency-free .xlsx builder (no PhpSpreadsheet / zip extension needed).
 *
 * Sheet: ['rows' => [[cell, ...], ...], 'cols' => [width, ...], 'merges' => ['A1:F1']]
 * Cell: scalar or [value, styleId]. Numbers stay numeric in Excel.
 * Styles: 0 normal, 1 title, 2 muted, 3 blue header, 4 bordered, 5 bordered centred, 6 section heading, 7 money (#,##0.00)
 */
class SimpleXlsx
{
    private const NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG = 'http://schemas.openxmlformats.org/package/2006/relationships';

    /** @param array<string, array> $sheets sheet name => sheet */
    public static function build(array $sheets): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>';
        $types = $sheetTags = $rels = '';
        $files = [];
        $i = 0;
        foreach ($sheets as $name => $sheet) {
            $i++;
            $types .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetTags .= '<sheet name="' . self::esc(mb_substr($name, 0, 31)) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $rels .= '<Relationship Id="rId' . $i . '" Type="' . self::REL . '/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            $files['xl/worksheets/sheet' . $i . '.xml'] = $xml . self::sheet($sheet);
        }

        return self::zip([
            '[Content_Types].xml' => $xml . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . $types . '</Types>',
            '_rels/.rels' => $xml . '<Relationships xmlns="' . self::PKG . '"><Relationship Id="rId1" Type="' . self::REL . '/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml' => $xml . '<workbook xmlns="' . self::NS . '" xmlns:r="' . self::REL . '"><sheets>' . $sheetTags . '</sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => $xml . '<Relationships xmlns="' . self::PKG . '">' . $rels
                . '<Relationship Id="rIdStyles" Type="' . self::REL . '/styles" Target="styles.xml"/></Relationships>',
            'xl/styles.xml' => $xml . self::styles(),
        ] + $files);
    }

    private static function sheet(array $sheet): string
    {
        $cols = '';
        foreach ($sheet['cols'] ?? [] as $i => $width) {
            $cols .= '<col min="' . ($i + 1) . '" max="' . ($i + 1) . '" width="' . $width . '" customWidth="1"/>';
        }

        $rows = '';
        foreach (array_values($sheet['rows']) as $r => $row) {
            $cells = '';
            foreach (array_values($row) as $c => $cell) {
                [$value, $style] = is_array($cell) ? $cell + [null, 0] : [$cell, 0];
                if ($value === null && !$style) {
                    continue;
                }
                $ref = self::column($c) . ($r + 1);
                $cells .= is_int($value) || is_float($value)
                    ? '<c r="' . $ref . '" s="' . $style . '"><v>' . $value . '</v></c>'
                    : '<c r="' . $ref . '" s="' . $style . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $value) . '</t></is></c>';
            }
            $rows .= '<row r="' . ($r + 1) . '">' . $cells . '</row>';
        }

        $merges = '';
        foreach ($sheet['merges'] ?? [] as $range) {
            $merges .= '<mergeCell ref="' . $range . '"/>';
        }

        return '<worksheet xmlns="' . self::NS . '">'
            . ($cols !== '' ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>' . $rows . '</sheetData>'
            . ($merges !== '' ? '<mergeCells count="' . count($sheet['merges']) . '">' . $merges . '</mergeCells>' : '')
            . '</worksheet>';
    }

    private static function styles(): string
    {
        $border = '<left style="thin"><color rgb="FFD1D5DB"/></left><right style="thin"><color rgb="FFD1D5DB"/></right>'
            . '<top style="thin"><color rgb="FFD1D5DB"/></top><bottom style="thin"><color rgb="FFD1D5DB"/></bottom><diagonal/>';
        $center = '<alignment horizontal="center" vertical="center"/>';

        return '<styleSheet xmlns="' . self::NS . '">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            . '<fonts count="5">'
            . '<font><sz val="10"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><color rgb="FF1F2937"/><name val="Calibri"/></font>'
            . '<font><sz val="9"/><color rgb="FF6B7280"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="10"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF0B5CAD"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0B5CAD"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border>' . $border . '</border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="8">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">' . $center . '</xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1"/>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">' . $center . '</xf>'
            . '<xf numFmtId="0" fontId="4" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="1" xfId="0" applyNumberFormat="1" applyBorder="1" applyAlignment="1">' . $center . '</xf>'
            . '</cellXfs></styleSheet>';
    }

    /** 0 => A, 25 => Z, 26 => AA */
    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** Minimal "stored" (uncompressed) zip archive — valid for .xlsx. */
    private static function zip(array $files): string
    {
        $time = ((int) date('H') << 11) | ((int) date('i') << 5) | intdiv((int) date('s'), 2);
        $date = (((int) date('Y') - 1980) << 9) | ((int) date('n') << 5) | (int) date('j');
        $data = $central = '';

        foreach ($files as $name => $content) {
            $crc = crc32($content);
            $size = strlen($content);
            $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0, 0, 0, 0, 0, strlen($data)) . $name;
            $data .= pack('VvvvvvVVVvv', 0x04034b50, 20, 0, 0, $time, $date, $crc, $size, $size, strlen($name), 0) . $name . $content;
        }

        return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), strlen($data), 0);
    }
}
