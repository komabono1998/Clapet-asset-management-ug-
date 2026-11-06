<?php
/**
 * Exports table-like data in many formats: csv, xlsx, json, xml, pdf, docx, html, txt.
 * No extensions required (zip and PDF are written by hand).
 *
 * $cols : list of column keys;  $rows : list of assoc arrays keyed by those columns.
 */
declare(strict_types=1);

const EXPORT_FORMATS = [
    'csv'  => ['CSV', 'text/csv; charset=utf-8'],
    'xlsx' => ['Excel (.xlsx)', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'json' => ['JSON', 'application/json; charset=utf-8'],
    'xml'  => ['XML', 'application/xml; charset=utf-8'],
    'pdf'  => ['PDF', 'application/pdf'],
    'docx' => ['Word (.docx)', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'html' => ['HTML', 'text/html; charset=utf-8'],
    'txt'  => ['Text', 'text/plain; charset=utf-8'],
];

function ex_label(string $k): string { return ucfirst(str_replace('_', ' ', $k)); }
function ex_x(string $s): string { return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
function ex_clean(string $s): string { return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s) ?? ''; }
/** Neutralises spreadsheet formulas (=, @, or +/- followed by anything that is not a plain number). */
function ex_cell(string $s): string {
    $s = ex_clean($s);
    return preg_match('/^(?:[=@\t\r]|[+\-](?![0-9 ().\-]*$))/', $s) ? "'" . $s : $s;
}

function export_send(array $cols, array $rows, string $fmt, string $title, string $base): void {
    if (!isset(EXPORT_FORMATS[$fmt])) { http_response_code(400); exit('Unknown format'); }
    $body = ('ex_' . $fmt)($cols, $rows, $title);
    $name = preg_replace('/[^a-z0-9_-]/i', '-', $base) . '-' . date('Y-m-d') . '.' . $fmt;
    header('Content-Type: ' . EXPORT_FORMATS[$fmt][1]);
    header('Content-Disposition: attachment; filename="' . $name . '"');
    header('Content-Length: ' . strlen($body));
    header('Cache-Control: no-store');
    echo $body;
    exit;
}

function ex_csv(array $cols, array $rows, string $t): string {
    $f = fopen('php://temp', 'w+');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, array_map('ex_label', $cols), ',', '"', '\\');
    foreach ($rows as $r) fputcsv($f, array_map(fn($c) => ex_cell((string)($r[$c] ?? '')), $cols), ',', '"', '\\');
    rewind($f);
    return (string)stream_get_contents($f);
}

function ex_json(array $cols, array $rows, string $t): string {
    $out = [];
    foreach ($rows as $r) { $o = []; foreach ($cols as $c) $o[$c] = (string)($r[$c] ?? ''); $out[] = $o; }
    return json_encode(['title' => $t, 'exported' => date('c'), 'count' => count($out), 'records' => $out], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function ex_xml(array $cols, array $rows, string $t): string {
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<export title="' . ex_x($t) . '" exported="' . date('c') . '" count="' . count($rows) . '">' . "\n";
    foreach ($rows as $r) {
        $x .= "  <record>\n";
        foreach ($cols as $c) {
            $tag = preg_replace('/[^a-z0-9_]/i', '_', $c);
            if (!preg_match('/^[a-z_]/i', $tag)) $tag = 'f_' . $tag;
            $x .= "    <$tag>" . ex_x(ex_clean((string)($r[$c] ?? ''))) . "</$tag>\n";
        }
        $x .= "  </record>\n";
    }
    return $x . "</export>\n";
}

function ex_txt(array $cols, array $rows, string $t): string {
    $o = strtoupper($t) . "\nExported " . date('Y-m-d H:i') . ' · ' . count($rows) . " record(s)\n" . str_repeat('=', 60) . "\n\n";
    foreach ($rows as $i => $r) {
        $o .= '#' . ($i + 1) . "\n";
        foreach ($cols as $c) if (($r[$c] ?? '') !== '') $o .= str_pad(ex_label($c), 24) . ': ' . str_replace("\n", ' ', (string)$r[$c]) . "\n";
        $o .= "\n";
    }
    return $o;
}

function ex_html(array $cols, array $rows, string $t): string {
    $h = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . ex_x($t) . '</title><style>body{font:14px/1.5 "Open Sans",Arial,sans-serif;color:#1b2a41;margin:32px}h1{font-family:Montserrat,Arial;color:#0A192F;border-bottom:3px solid #D4AF37;padding-bottom:8px}table{border-collapse:collapse;width:100%;margin-top:18px;font-size:12px}th{background:#0A192F;color:#fff;text-align:left;padding:8px}td{border-bottom:1px solid #e3e7ee;padding:7px 8px;vertical-align:top}tr:nth-child(even) td{background:#F8F9FA}small{color:#5d6c82}@media print{body{margin:10mm}}</style></head><body><h1>' . ex_x($t) . '</h1><small>Exported ' . date('Y-m-d H:i') . ' · ' . count($rows) . ' record(s) · CLAPET ASSET MANAGEMENT UG - SMC</small><table><tr>';
    foreach ($cols as $c) $h .= '<th>' . ex_x(ex_label($c)) . '</th>';
    $h .= '</tr>';
    foreach ($rows as $r) { $h .= '<tr>'; foreach ($cols as $c) $h .= '<td>' . nl2br(ex_x((string)($r[$c] ?? ''))) . '</td>'; $h .= '</tr>'; }
    return $h . '</table></body></html>';
}

/* ---------------------------------------------------------------- zip (store only) */
function ex_zip(array $files): string {
    $data = $central = '';
    $off = 0;
    $dt = getdate();
    $time = ($dt['hours'] << 11) | ($dt['minutes'] << 5) | intdiv($dt['seconds'], 2);
    $date = (($dt['year'] - 1980) << 9) | ($dt['mon'] << 5) | $dt['mday'];
    foreach ($files as $name => $content) {
        $crc = crc32($content);
        $len = strlen($content);
        $hdr = pack('VvvvvvVVVvv', 0x04034b50, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0) . $name;
        $data .= $hdr . $content;
        $central .= pack('VvvvvvvVVVvvvvvVV', 0x02014b50, 20, 20, 0x0800, 0, $time, $date, $crc, $len, $len, strlen($name), 0, 0, 0, 0, 0, $off) . $name;
        $off += strlen($hdr) + $len;
    }
    return $data . $central . pack('VvvvvVVv', 0x06054b50, 0, 0, count($files), count($files), strlen($central), $off, 0);
}

function ex_col(int $i): string { $s = ''; for ($i++; $i > 0; $i = intdiv($i - 1, 26)) $s = chr(65 + ($i - 1) % 26) . $s; return $s; }

function ex_xlsx(array $cols, array $rows, string $t): string {
    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews><sheetData>';
    $sheet .= '<row r="1">';
    foreach ($cols as $i => $c) $sheet .= '<c r="' . ex_col($i) . '1" s="1" t="inlineStr"><is><t>' . ex_x(ex_label($c)) . '</t></is></c>';
    $sheet .= '</row>';
    foreach ($rows as $n => $r) {
        $sheet .= '<row r="' . ($n + 2) . '">';
        foreach ($cols as $i => $c) $sheet .= '<c r="' . ex_col($i) . ($n + 2) . '" t="inlineStr"><is><t xml:space="preserve">' . ex_x(ex_cell((string)($r[$c] ?? ''))) . '</t></is></c>';
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';
    return ex_zip([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="' . ex_x(mb_substr(preg_replace('/[\[\]\/\\\\*?:]/', ' ', $t), 0, 28) ?: 'Export') . '" sheetId="1" r:id="rId1"/></sheets></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts><fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF0A192F"/></patternFill></fill></fills><borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders><cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs><cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs></styleSheet>',
        'xl/worksheets/sheet1.xml' => $sheet,
    ]);
}

function ex_docx(array $cols, array $rows, string $t): string {
    $p = fn(string $txt, bool $b = false, int $sz = 22, string $color = '1B2A41') => '<w:p><w:pPr><w:spacing w:after="60"/></w:pPr><w:r><w:rPr>' . ($b ? '<w:b/>' : '') . '<w:color w:val="' . $color . '"/><w:sz w:val="' . $sz . '"/></w:rPr><w:t xml:space="preserve">' . ex_x(ex_clean($txt)) . '</w:t></w:r></w:p>';
    $body = $p($t, true, 36, '0A192F') . $p('Exported ' . date('Y-m-d H:i') . ' · ' . count($rows) . ' record(s) · CLAPET ASSET MANAGEMENT UG - SMC', false, 18, '5D6C82');
    foreach ($rows as $i => $r) {
        $body .= $p('Record ' . ($i + 1), true, 26, '8A6D12');
        $body .= '<w:tbl><w:tblPr><w:tblW w:w="5000" w:type="pct"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="E3E7EE"/><w:bottom w:val="single" w:sz="4" w:color="E3E7EE"/><w:insideH w:val="single" w:sz="4" w:color="E3E7EE"/></w:tblBorders></w:tblPr>';
        foreach ($cols as $c) {
            if (($r[$c] ?? '') === '') continue;
            $body .= '<w:tr><w:tc><w:tcPr><w:tcW w:w="1500" w:type="pct"/></w:tcPr>' . $p(ex_label($c), true, 20, '0A192F') . '</w:tc><w:tc><w:tcPr><w:tcW w:w="3500" w:type="pct"/></w:tcPr>' . $p((string)$r[$c], false, 20) . '</w:tc></w:tr>';
        }
        $body .= '</w:tbl>' . $p('');
    }
    return ex_zip([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>',
    ]);
}

/* ---------------------------------------------------------------- pdf */
function ex_pdf(array $cols, array $rows, string $t): string {
    $w = fn(string $s): string => (string)@iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', ex_clean($s));
    $esc = fn(string $s): string => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $w($s));
    $lines = [['T', $t], ['S', 'Exported ' . date('Y-m-d H:i') . '  |  ' . count($rows) . ' record(s)  |  CLAPET ASSET MANAGEMENT UG - SMC'], ['G', '']];
    foreach ($rows as $i => $r) {
        $lines[] = ['H', 'Record ' . ($i + 1)];
        foreach ($cols as $c) {
            if (($r[$c] ?? '') === '') continue;
            $wrapped = explode("\n", wordwrap(str_replace(["\r", "\n"], ' ', (string)$r[$c]), 78, "\n", true));
            foreach ($wrapped as $k => $ln) $lines[] = ['R', $k === 0 ? ex_label($c) : '', $ln];
        }
        $lines[] = ['G', ''];
    }
    $pages = []; $cur = ''; $y = 800;
    $newPage = function () use (&$pages, &$cur, &$y) { if ($cur !== '') $pages[] = $cur; $cur = ''; $y = 800; };
    foreach ($lines as $l) {
        $need = ['T' => 30, 'S' => 20, 'G' => 8, 'H' => 24, 'R' => 14][$l[0]];
        if ($y - $need < 50) $newPage();
        switch ($l[0]) {
            case 'T': $cur .= "BT /F2 18 Tf 0.04 0.1 0.18 rg 40 $y Td (" . $esc($l[1]) . ") Tj ET\n0.83 0.69 0.22 rg 40 " . ($y - 8) . " 200 2 re f\n"; break;
            case 'S': $cur .= "BT /F1 8 Tf 0.36 0.42 0.51 rg 40 $y Td (" . $esc($l[1]) . ") Tj ET\n"; break;
            case 'H': $cur .= "0.04 0.1 0.18 rg 40 " . ($y - 6) . " 515 18 re f\nBT /F2 10 Tf 1 1 1 rg 46 " . ($y - 1) . " Td (" . $esc($l[1]) . ") Tj ET\n"; break;
            case 'R': $cur .= "BT /F2 9 Tf 0.04 0.1 0.18 rg 46 $y Td (" . $esc($l[1]) . ") Tj ET\nBT /F1 9 Tf 0.1 0.16 0.25 rg 170 $y Td (" . $esc($l[2]) . ") Tj ET\n"; break;
        }
        $y -= $need;
    }
    $newPage();
    if (!$pages) $pages[] = '';
    $objs = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>', 4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
    $kids = [];
    foreach ($pages as $i => $content) {
        $po = 5 + $i * 2; $co = $po + 1;
        $footer = "BT /F1 8 Tf 0.36 0.42 0.51 rg 40 28 Td (Page " . ($i + 1) . " of " . count($pages) . ") Tj ET\n";
        $stream = $content . $footer;
        $objs[$po] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents $co 0 R >>";
        $objs[$co] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
        $kids[] = "$po 0 R";
    }
    $objs[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
    ksort($objs);
    $pdf = "%PDF-1.4\n"; $xref = [];
    foreach ($objs as $n => $o) { $xref[$n] = strlen($pdf); $pdf .= "$n 0 obj\n$o\nendobj\n"; }
    $max = max(array_keys($objs));
    $start = strlen($pdf);
    $pdf .= "xref\n0 " . ($max + 1) . "\n0000000000 65535 f \n";
    for ($n = 1; $n <= $max; $n++) $pdf .= sprintf("%010d 00000 n \n", $xref[$n] ?? 0);
    return $pdf . "trailer\n<< /Size " . ($max + 1) . " /Root 1 0 R >>\nstartxref\n$start\n%%EOF";
}
