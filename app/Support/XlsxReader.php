<?php

namespace App\Support;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal, dependency-free .xlsx reader (values only, no formulas evaluated —
 * cached values are used). Enough to stage a treasurer's spreadsheet for review.
 */
final class XlsxReader
{
    /** @return array<string, array<int, array<string, string>>> sheet name => rows (column letter => value) */
    public static function read(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Not a valid .xlsx file.');
        }
        $strings = [];
        if (($xml = $zip->getFromName('xl/sharedStrings.xml')) !== false) {
            foreach (self::xml($xml)->si as $si) {
                $text = '';
                foreach ($si->xpath('.//*[local-name()="t"]') as $t) {
                    $text .= (string) $t;
                }
                $strings[] = $text;
            }
        }
        $rels = [];
        foreach (self::xml($zip->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $r) {
            $rels[(string) $r['Id']] = 'xl/'.ltrim(str_replace('/xl/', '', (string) $r['Target']), '/');
        }
        $sheets = [];
        $wb = self::xml($zip->getFromName('xl/workbook.xml'));
        foreach ($wb->sheets->sheet as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $file = $rels[$rid] ?? null;
            if (! $file || ($sx = $zip->getFromName($file)) === false) {
                continue;
            }
            $rows = [];
            foreach (self::xml($sx)->sheetData->row as $row) {
                $cells = [];
                foreach ($row->c as $c) {
                    $v = isset($c->v) ? (string) $c->v : (isset($c->is->t) ? (string) $c->is->t : null);
                    if ($v === null) {
                        continue;
                    }
                    if ((string) $c['t'] === 's') {
                        $v = $strings[(int) $v] ?? '';
                    }
                    $cells[preg_replace('/\d+/', '', (string) $c['r'])] = trim($v);
                }
                if ($cells) {
                    $rows[(int) $row['r']] = $cells;
                }
            }
            $sheets[(string) $s['name']] = $rows;
        }
        $zip->close();

        return $sheets;
    }

    private static function xml(string|false $content): SimpleXMLElement
    {
        if ($content === false) {
            throw new RuntimeException('Spreadsheet is missing a required part.');
        }
        $prev = libxml_use_internal_errors(true);
        // LIBXML_NONET: never fetch external entities from an uploaded file.
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($prev);
        if (! $xml) {
            throw new RuntimeException('Spreadsheet XML could not be read.');
        }

        return $xml;
    }
}
