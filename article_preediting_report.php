<?php
/**
 * Article Pre-editing Report (Client-wise, completion-gated) - XLS export
 * ----------------------------------------------------------------------
 * Lists, per client, every article and the CE/PE/XML work done on it at the
 * FP stage over a DATE RANGE. An article is reported ONLY when the LAST
 * department in that client's configured pipeline is Completed (e.g. IAP =>
 * XML must be completed, not just CE).
 *
 * Output: an Excel-readable .xls file (HTML-table based SpreadsheetML-lite,
 * no external library needed). Opens directly in Excel / LibreOffice.
 *
 * Usage (CLI):
 *   php article_preediting_report.php                          # 2026-09-01 .. 2026-09-30, saved to ./
 *   php article_preediting_report.php 2026-09-01 2026-09-30    # explicit range
 *   php article_preediting_report.php 2026-09-01 2026-09-30 /path/out.xls
 *   php article_preediting_report.php 2026-09-01 2026-09-30 --mail   # also email it
 *
 * Usage (browser): article_preediting_report.php?from=2026-09-01&to=2026-09-30
 *   -> streams the .xls as a download.
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

// --- Resolve inputs (CLI args or query string) ------------------------------
$isCli = PHP_SAPI === 'cli';
$sendMail = false;
$outPath  = null;

if ($isCli) {
    $args = array_slice($argv, 1);
    $sendMail = in_array('--mail', $args, true);
    $args = array_values(array_filter($args, fn($a) => $a !== '--mail'));
    $dateFrom = $args[0] ?? DEFAULT_FROM;
    $dateTo   = $args[1] ?? DEFAULT_TO;
    $outPath  = $args[2] ?? null;
} else {
    $dateFrom = $_GET['from'] ?? DEFAULT_FROM;
    $dateTo   = $_GET['to']   ?? DEFAULT_TO;
}

foreach ([$dateFrom, $dateTo] as $d) {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) || !strtotime($d)) {
        fail("Invalid date '{$d}'. Use YYYY-MM-DD.");
    }
}
if ($dateFrom > $dateTo) {
    fail("From date ({$dateFrom}) is after To date ({$dateTo}).");
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
    fail('DB connection failed: ' . $e->getMessage());
}

// --- Fetch + build the XLS --------------------------------------------------
$rows = fetchArticleRows($pdo, $dateFrom, $dateTo, $config['filters']['stage']);
$xls  = renderReportXls($rows, $dateFrom, $dateTo);

$fileName = sprintf('Article_PreEditing_Report_%s_to_%s.xls', $dateFrom, $dateTo);

if (!$isCli) {
    // Browser: stream as a download.
    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    echo $xls;
    exit(0);
}

// CLI: write to disk.
if ($outPath === null) {
    $outPath = __DIR__ . '/' . $fileName;
} elseif (is_dir($outPath)) {
    $outPath = rtrim($outPath, '/\\') . '/' . $fileName;
}
if (file_put_contents($outPath, $xls) === false) {
    fail("Could not write {$outPath}");
}
echo "XLS saved: {$outPath} (" . count($rows) . " articles, {$dateFrom} to {$dateTo}).\n";

if ($sendMail) {
    $subject = sprintf('Article Pre-editing Report - %s to %s', $dateFrom, $dateTo);
    $body    = sprintf('<p>Please find attached the Article Pre-editing Report for %s to %s (%d articles).</p>',
        h($dateFrom), h($dateTo), count($rows));
    $sent = sendReport($config['mail'], $subject, $body, $outPath);
    echo $sent ? "Report emailed.\n" : "Emailing FAILED.\n";
    exit($sent ? 0 : 1);
}
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

    $x  = '<html xmlns:o="urn:schemas-microsoft-com:office:office" '
        . 'xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    $x .= '<head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8">'
        . '<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet>'
        . '<x:Name>PreEditing Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/>'
        . '</x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->'
        . '</head><body>';

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

    $x .= '</table></body></html>';

    return "\xEF\xBB\xBF" . $x; // UTF-8 BOM so Excel reads encoding correctly
}


/* =========================================================================
 * Mail (optional, with the XLS attached)
 * ======================================================================= */

function sendReport(array $mail, string $subject, string $html, string $attachment): bool
{
    if (!empty($mail['use_smtp'])) {
        return sendViaPhpMailer($mail, $subject, $html, $attachment);
    }
    return sendViaMail($mail, $subject, $html, $attachment);
}

/** Preferred: PHPMailer over SMTP. */
function sendViaPhpMailer(array $mail, string $subject, string $html, string $attachment): bool
{
    $autoload = __DIR__ . '/smtpmail/phpmailer/class.phpmailer.php';
    if (!is_file($autoload)) {
        fwrite(STDERR, "PHPMailer not installed; falling back to mail().\n");
        return sendViaMail($mail, $subject, $html, $attachment);
    }
    require_once $autoload;

    $m = new PHPMailer();
    try {
        $m->isSMTP();
        $m->Host       = $mail['smtp_host'];
        $m->Port       = (int) $mail['smtp_port'];
        $m->SMTPAuth   = true;
        $m->Username   = $mail['smtp_user'];
        $m->Password   = $mail['smtp_pass'];
        $m->SMTPSecure = $mail['smtp_secure'];

        $m->setFrom($mail['from_email'], $mail['from_name']);
        foreach ($mail['to'] as $addr => $name) {
            $m->addAddress($addr, $name);
        }
        foreach (($mail['cc'] ?? []) as $addr => $name) {
            $m->addCC($addr, $name);
        }

        $m->isHTML(true);
        $m->Subject = $subject;
        $m->Body    = $html;
        $m->AltBody = strip_tags($html);
        $m->addAttachment($attachment, basename($attachment));

        $m->send();
        return true;
    } catch (Throwable $e) {
        fwrite(STDERR, 'PHPMailer error: ' . $e->getMessage() . "\n");
        return false;
    }
}

/** Fallback: PHP mail() with a MIME attachment. */
function sendViaMail(array $mail, string $subject, string $html, string $attachment): bool
{
    $to       = implode(', ', array_keys($mail['to']));
    $boundary = 'b' . md5((string) microtime(true));

    $headers   = [];
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = sprintf('From: %s <%s>', $mail['from_name'], $mail['from_email']);
    if (!empty($mail['cc'])) {
        $headers[] = 'Cc: ' . implode(', ', array_keys($mail['cc']));
    }
    $headers[] = "Content-Type: multipart/mixed; boundary=\"{$boundary}\"";

    $body  = "--{$boundary}\r\nContent-Type: text/html; charset=UTF-8\r\n\r\n{$html}\r\n";
    $body .= "--{$boundary}\r\nContent-Type: application/vnd.ms-excel; name=\"" . basename($attachment) . "\"\r\n"
           . "Content-Transfer-Encoding: base64\r\n"
           . 'Content-Disposition: attachment; filename="' . basename($attachment) . "\"\r\n\r\n"
           . chunk_split(base64_encode((string) file_get_contents($attachment)))
           . "--{$boundary}--";

    return mail($to, $subject, $body, implode("\r\n", $headers));
}


/* =========================================================================
 * Helpers
 * ======================================================================= */

function fail(string $msg): void
{
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $msg . "\n");
    } else {
        http_response_code(400);
        echo h($msg);
    }
    exit(1);
}

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
