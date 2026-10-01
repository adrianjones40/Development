<?php
/**
 * Article Pre-editing Report (Client-wise, completion-gated)
 * ----------------------------------------------------------
 * Web page: pick a From date and To date, click "Show report" to see the
 * result grid; the XLS file downloads automatically.
 *
 * An article is reported ONLY when the LAST department in that client's
 * configured pipeline is Completed (e.g. IAP => XML must be completed).
 * Only FP-stage transactions are considered.
 *
 * URL: article_preediting_report.php?from=2026-09-01&to=2026-09-30
 *      (&download=1 returns just the .xls file)
 */

date_default_timezone_set('Asia/Kolkata'); // adjust to your timezone

$config = require __DIR__ . '/config.php';

/* -------------------------------------------------------------------------
 * CLIENT-WISE COMPLETION CONFIG (unchanged)
 * ---------------------------------------------------------------------- */
const STAGE_DEPT = [
    'Pre-Clean'   => 22,   // <-- CONFIRM dept id (never an end dept)
    'Pre-editing' => 2,    // PE  (known)
    'CE'          => 1,    // CE  (known)
    'XML'         => 5,    // <-- CONFIRM dept id (end dept for AKA/GleamPub/IAP)
    'CE-ELX'      => 32,   // <-- CONFIRM dept id (end dept for EDP-Sci)
];

const CLIENT_STAGES = [
    'ACG'      => ['Pre-Clean', 'CE'],
    'AKA'      => ['Pre-editing', 'CE', 'XML'],
    'ASP'      => ['Pre-Clean', 'CE'],
    'EDP-Sci'  => ['CE-ELX'],
    'EOS'      => ['Pre-Clean', 'CE'],
    'GleamPub' => ['Pre-editing', 'CE', 'XML'],
    'IAP'      => ['Pre-editing', 'CE', 'XML'],
    'PEERJ'    => ['Pre-Clean', 'CE'],
    'SIF'      => ['Pre-editing'],
    'TSP'      => ['Pre-Clean', 'CE'],
];

const DEFAULT_FROM = '2026-09-01';
const DEFAULT_TO   = '2026-09-30';

// --- Resolve inputs (query string) -------------------------------------------
$dateFrom  = $_GET['from'] ?? DEFAULT_FROM;
$dateTo    = $_GET['to']   ?? DEFAULT_TO;
$submitted = isset($_GET['from']) || isset($_GET['to']);
$download  = isset($_GET['download']);

// First visit: just the date picker.
if (!$submitted) {
    echo renderPage(null, $dateFrom, $dateTo, null);
    exit(0);
}

$error = null;
foreach ([$dateFrom, $dateTo] as $d) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
        $error = "Invalid date '{$d}'. Use YYYY-MM-DD.";
    }
}
if (!$error && $dateFrom > $dateTo) {
    $error = "From date ({$dateFrom}) is after To date ({$dateTo}).";
}
if ($error) {
    echo renderPage(null, $dateFrom, $dateTo, $error);
    exit(0);
}

// --- Connect (PDO + prepared statements) ------------------------------------
$db  = $config['db'];
$dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset={$db['charset']}";
try {
    $pdo = new PDO($dsn, $db['user'], $db['pass'], [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    echo renderPage(null, $dateFrom, $dateTo, 'Database connection failed.');
    exit(1);
}

// --- Fetch + build the XLS --------------------------------------------------
$rows = fetchArticleRows($pdo, $dateFrom, $dateTo, $config['filters']['stage']);
$xls  = renderReportXls($rows, $dateFrom, $dateTo);

$fileName = sprintf('Article_PreEditing_Report_%s_to_%s.xls', $dateFrom, $dateTo);

if (!$download) {
    // Show the grid; the page auto-starts the XLS download.
    echo renderPage($rows, $dateFrom, $dateTo, null);
    exit(0);
}

header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Cache-Control: max-age=0');
echo $xls;
exit(0);


/* =========================================================================
 * Data access
 * ======================================================================= */

/**
 * One aggregated row per article whose FP work started within
 * [$dateFrom, $dateTo], gated so that only articles whose client's END
 * department is Completed are returned.
 */
function fetchArticleRows(PDO $pdo, string $dateFrom, string $dateTo, string $stage): array
{
    $deptIds   = array_values(array_unique(array_map('intval', STAGE_DEPT)));
    $deptInSql = implode(',', $deptIds) ?: '0';

    // Client -> end (last) department id, built from the pipeline config.
    $endDeptByClient = [];
    foreach (CLIENT_STAGES as $client => $stages) {
        $endStage = end($stages);
        if (isset(STAGE_DEPT[$endStage])) {
            $endDeptByClient[$client] = (int) STAGE_DEPT[$endStage];
        }
    }

    $params = [
        ':stage'     => $stage,
        ':stage_end' => $stage,
        ':from_dt'   => $dateFrom . ' 00:00:00',
        // Exclusive upper bound = start of the day after $dateTo (index friendly).
        ':to_dt'     => date('Y-m-d', strtotime($dateTo . ' +1 day')) . ' 00:00:00',
    ];

    // Inline derived table:  cfg(cust_name, end_dept)
    $cfgParts = [];
    $k = 0;
    foreach ($endDeptByClient as $client => $endDept) {
        $cfgParts[]        = "SELECT :cn{$k} AS cust_name, " . (int) $endDept . " AS end_dept";
        $params[":cn{$k}"] = $client;
        $k++;
    }
    $cfgSql = $cfgParts ? implode(' UNION ALL ', $cfgParts)
                        : "SELECT NULL AS cust_name, 0 AS end_dept";

    $sql = "
        SELECT
            c.cust_name                        AS client,
            j.j_code                           AS journal,
            d.job_id                           AS article_id,
            DATE_FORMAT(d.recv_dt, '%d-%b-%Y') AS recv_dt,
            d.inw_type                         AS inw_type,
            d.page_count                       AS page_count,
            d.manuscript_count                 AS mss_pages,

            /* Received into pre-editing = first CE/PE start on the file. */
            MIN(t.start_time)                  AS received_dt,

            /* Completed and moved to pagination = last CE/PE/XML end. */
            MAX(t.end_time)                    AS completed_dt,

            /* Editor(s) who worked the file. */
            GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ')
                                               AS preeditor_name,

            /* Actual time spent = sum of every completed session duration. */
            SUM(TIMESTAMPDIFF(SECOND, t.start_time, t.end_time)) AS spent_seconds

        FROM inw_transactions      t
        JOIN inw_inward_dtl        d ON d.id   = t.project_id
        JOIN users                 u ON u.id   = t.process_user
        LEFT JOIN adm_customer_master c ON c.id  = d.cust_id
        LEFT JOIN adm_journals     j ON j.j_id = d.j_id

        /* Client-wise pipeline config; also limits output to configured clients. */
        JOIN ( {$cfgSql} ) cfg ON cfg.cust_name = c.cust_name

        WHERE t.stage = :stage
          AND t.dept IN ({$deptInSql})
          AND t.current_status = 'Completed'
          AND t.start_time >= :from_dt
          AND t.start_time <  :to_dt

          /* Gate: the client's END department must itself be Completed. */
          AND EXISTS (
                SELECT 1
                FROM inw_transactions et
                WHERE et.project_id = d.id
                  AND et.stage = :stage_end
                  AND et.dept  = cfg.end_dept
                  AND et.current_status = 'Completed'
          )

        GROUP BY d.id, c.cust_name, j.j_code, d.job_id,
                 d.recv_dt, d.inw_type, d.page_count, d.manuscript_count
        ORDER BY c.cust_name, j.j_code, d.job_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================================
 * XLS rendering (Excel opens HTML tables saved as .xls)
 * ======================================================================= */

function renderReportXls(array $rows, string $dateFrom, string $dateTo): string
{
    $x  = '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
        . 'xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    $x .= '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
        . '<x:Name>PreEditing Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/>'
        . '</x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->'
        . '</head><body>' . buildReportTable($rows, $dateFrom, $dateTo) . '</body></html>';

    return "\xEF\xBB\xBF" . $x; // UTF-8 BOM so Excel reads encoding correctly
}

/** The report grid (shared by the on-screen page and the XLS file). */
function buildReportTable(array $rows, string $dateFrom, string $dateTo): string
{
    $thStyle = 'style="border:1px solid #444;padding:4px;background:#1f4e78;color:#ffffff;font-weight:bold;text-align:left;vertical-align:top;"';
    $tdStyle = 'style="border:1px solid #999;padding:3px;vertical-align:top;"';
    // mso-number-format:'\@' forces text so IDs / numeric-looking values keep their form.
    $txt     = 'style="border:1px solid #999;padding:3px;vertical-align:top;mso-number-format:\'\\@\';"';

    $headers = [
        'Sn',
        'Client',
        'Journal',
        'Article ID',
        'Recv Date',
        'Input Type',
        'Proof page count',
        'MSS pages',
        'Date received into pre-editing',
        'Time of receipt into pre-editing',
        'Date CE / PE completed and moved to pagination',
        'Time of CE / PE completion and moved to pagination',
        'Pre-editor name',
        'Actual pre-editing time spent on the file',
    ];

    $x = '';
    $x .= '<table border="1" cellspacing="0" cellpadding="3">';
    $x .= '<tr><td colspan="14" style="font-size:14pt;font-weight:bold;">'
        . 'Article Pre-editing Report (CE / PE / XML)</td></tr>';
    $x .= '<tr><td colspan="14">Stage: FP | Period: '
        . h(date('d-M-Y', strtotime($dateFrom))) . ' to ' . h(date('d-M-Y', strtotime($dateTo)))
        . ' | Article included only when the client\'s final department is Completed.</td></tr>';

    $x .= '<tr>';
    foreach ($headers as $head) {
        $x .= "<th {$thStyle}>" . h($head) . '</th>';
    }
    $x .= '</tr>';

    $sn           = 0;
    $grandSeconds = 0;

    foreach ($rows as $row) {
        $sn++;
        $spent         = $row['spent_seconds'] !== null ? (int) $row['spent_seconds'] : 0;
        $grandSeconds += $spent;

        $x .= '<tr>';
        $x .= "<td {$tdStyle}>" . $sn . '</td>';
        $x .= "<td {$txt}>" . h($row['client'] ?? '-') . '</td>';
        $x .= "<td {$txt}>" . h($row['journal'] ?? '-') . '</td>';
        $x .= "<td {$txt}>" . h($row['article_id'] ?? '-') . '</td>';
        $x .= "<td {$txt}>" . h($row['recv_dt'] ?? '-') . '</td>';
        $x .= "<td {$txt}>" . h($row['inw_type'] ?? '-') . '</td>';
        $x .= "<td {$tdStyle}>" . h($row['page_count'] ?? '-') . '</td>';
        $x .= "<td {$tdStyle}>" . h($row['mss_pages'] ?? '-') . '</td>';
        $x .= "<td {$txt}>" . h(fmtDate($row['received_dt'])) . '</td>';
        $x .= "<td {$txt}>" . h(fmtTime($row['received_dt'])) . '</td>';
        $x .= "<td {$txt}>" . h(fmtDate($row['completed_dt'])) . '</td>';
        $x .= "<td {$txt}>" . h(fmtTime($row['completed_dt'])) . '</td>';
        $x .= "<td {$txt}>" . h($row['preeditor_name'] ?? '-') . '</td>';
        $x .= "<td {$txt}>" . h(secondsToHms($spent)) . '</td>';
        $x .= '</tr>';
    }

    if (!$rows) {
        $x .= '<tr><td colspan="14">No completed articles found for this period.</td></tr>';
    } else {
        $x .= '<tr><td colspan="13" style="border:1px solid #444;text-align:right;background:#1f4e78;'
            . 'color:#ffffff;font-weight:bold;">Total pre-editing time spent</td>'
            . '<td style="border:1px solid #444;background:#1f4e78;color:#ffffff;font-weight:bold;'
            . 'mso-number-format:\'\\@\';">' . h(secondsToHms($grandSeconds)) . '</td></tr>';
    }

    $x .= '</table>';

    return $x;
}

/** Browser page: date pickers, grid, Download XLS button. */
function renderPage(?array $rows, string $dateFrom, string $dateTo, ?string $error): string
{
    $q = http_build_query(['from' => $dateFrom, 'to' => $dateTo, 'download' => 1]);

    $o  = '<!doctype html><html><head><meta charset="utf-8"><title>Article Pre-editing Report</title>'
        . '<style>body{font-family:Calibri,Arial,sans-serif;font-size:13px;margin:16px;color:#222}'
        . 'form{margin:0 0 14px}label{margin-right:12px}'
        . 'button,a.btn{background:#1f4e78;color:#fff;border:0;padding:6px 14px;margin-right:8px;'
        . 'text-decoration:none;cursor:pointer;font-size:13px;border-radius:3px}'
        . '.err{color:#b00020;margin-bottom:10px}.wrap{overflow:auto}</style></head><body>';
    $o .= '<h2 style="margin:0 0 10px">Article Pre-editing Report (CE / PE / XML)</h2>';
    $o .= '<form method="get">'
        . '<label>From date <input type="date" name="from" required value="' . h($dateFrom) . '"></label>'
        . '<label>To date <input type="date" name="to" required value="' . h($dateTo) . '"></label>'
        . '<button type="submit">Show report</button>';
    if ($rows !== null) {
        $o .= '<span>' . count($rows) . ' article(s) &mdash; XLS download started '
            . '(<a href="?' . h($q) . '">download again</a>)</span>';
    }
    $o .= '</form>';
    if ($error) {
        $o .= '<div class="err">' . h($error) . '</div>';
    }
    if ($rows !== null) {
        $o .= '<div class="wrap">' . buildReportTable($rows, $dateFrom, $dateTo) . '</div>'
            . '<iframe src="?' . h($q) . '" style="display:none" title="XLS download"></iframe>';
    }
    return $o . '</body></html>';
}


/* =========================================================================
 * Helpers
 * ======================================================================= */

/** HTML-escape. */
function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Format a datetime string as a date (dd-Mon-yyyy). Returns '-' when empty. */
function fmtDate(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime($dt);
    return $ts ? date('d-M-Y', $ts) : $dt;
}

/** Format a datetime string as HH:MM (12h). Returns '-' when empty. */
function fmtTime(?string $dt): string
{
    if (!$dt) {
        return '-';
    }
    $ts = strtotime($dt);
    return $ts ? date('h:i A', $ts) : $dt;
}

/** Convert seconds to H:MM:SS. */
function secondsToHms(int $seconds): string
{
    $seconds = max(0, $seconds);
    $h = intdiv($seconds, 3600);
    $m = intdiv($seconds % 3600, 60);
    $s = $seconds % 60;
    return sprintf('%d:%02d:%02d', $h, $m, $s);
}
