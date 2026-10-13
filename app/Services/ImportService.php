<?php

namespace App\Services;

use App\Models\ImportBatch;
use App\Models\ImportCandidate;
use App\Models\Member;
use App\Models\User;
use App\Support\Audit;
use App\Support\XlsxReader;

/**
 * Stages people found in a spreadsheet (or CSV) for human review. It never
 * creates members, users, phone numbers or money records on its own.
 */
class ImportService
{
    public static function normalizeName(string $name): string
    {
        $n = mb_strtolower(trim($name));
        $n = preg_replace('/\b(mr|mrs|miss|ms|dr|bw|bi)\.?\s+/u', '', $n) ?? $n;
        $n = preg_replace('/[^\p{L}\s]/u', ' ', $n) ?? $n;

        return trim(preg_replace('/\s+/', ' ', $n) ?? $n);
    }

    public function stageSpreadsheet(string $path, string $originalName, User $by): ImportBatch
    {
        $people = [];
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $sheets = $ext === 'csv' ? ['CSV' => $this->readCsv($path)] : XlsxReader::read($path);

        foreach ($sheets as $sheet => $rows) {
            // Find the header row: a cell reading JINA / NAME.
            $headerRow = null;
            $nameCol = null;
            foreach ($rows as $r => $cells) {
                foreach ($cells as $col => $v) {
                    if (in_array(mb_strtoupper(trim($v)), ['JINA', 'NAME', 'MWANACHAMA'], true)) {
                        $headerRow = $r;
                        $nameCol = $col;
                        break 2;
                    }
                }
            }
            if (! $headerRow) {
                continue;
            }
            $headers = $rows[$headerRow];
            foreach ($rows as $r => $cells) {
                if ($r <= $headerRow || empty($cells[$nameCol]) || is_numeric($cells[$nameCol])) {
                    continue;
                }
                $raw = $cells[$nameCol];
                $key = self::normalizeName($raw);
                if ($key === '' || str_contains($key, 'total') || str_contains($key, 'jumla')) {
                    continue;
                }
                $people[$key]['raw'][] = $raw;
                foreach ($cells as $col => $v) {
                    if ($col !== $nameCol && isset($headers[$col]) && $v !== '') {
                        $people[$key]['data'][$sheet][$headers[$col]] = is_numeric($v) ? (int) round((float) $v) : $v;
                    }
                }
            }
        }

        $batch = ImportBatch::create(['source' => $ext === 'csv' ? 'csv' : 'spreadsheet', 'filename' => mb_substr($originalName, 0, 200), 'uploaded_by' => $by->id, 'status' => 'review', 'summary' => ['people' => count($people), 'sheets' => array_keys($sheets)]]);
        $members = Member::all(['id', 'full_name', 'preferred_name']);
        foreach ($people as $key => $p) {
            ImportCandidate::create([
                'import_batch_id' => $batch->id,
                'raw_name' => implode(' / ', array_unique($p['raw'])),
                'normalized_name' => $key,
                'proposed_member_id' => $this->bestMatch($key, $members)?->id,
                'data' => $p['data'] ?? [],
            ]);
        }
        Audit::record('members.import_staged', $batch, "Import staged from {$originalName}", ['people' => count($people)]);

        return $batch;
    }

    private function bestMatch(string $key, $members): ?Member
    {
        $best = null;
        $bestScore = 0.0;
        foreach ($members as $m) {
            foreach (array_filter([$m->full_name, $m->preferred_name]) as $name) {
                similar_text($key, self::normalizeName($name), $pct);
                if ($pct > $bestScore) {
                    $bestScore = $pct;
                    $best = $m;
                }
            }
        }

        return $bestScore >= 80 ? $best : null;
    }

    private function readCsv(string $path): array
    {
        $rows = [];
        $h = fopen($path, 'r');
        $i = 1;
        while (($line = fgetcsv($h)) !== false && $i < 5000) {
            $cells = [];
            foreach ($line as $c => $v) {
                $cells[chr(65 + min($c, 25))] = trim((string) $v);
            }
            $rows[$i++] = $cells;
        }
        fclose($h);

        return $rows;
    }
}
