<?php
/*
 * Debug / diagnostics for conversion_master_status_report.php
 *
 * Open: debug_master_status_report.php
 * Checks, without changing any data:
 *   1. required files, PHP version/extensions, session values
 *   2. every table + column the report reads (flags anything missing, e.g. a column that
 *      still only exists on the old journal tables after the books conversion)
 *   3. a dry run of the report's main queries (LIMIT 1) with timing and any SQL error
 *   4. Consolidated report  - the real queries with your filters: open projects (WIP page) and delivered projects
 *   5. Delivery Performance - the real queries with your filters: counts, Ahead/On time/Delay totals,
 *      sample rows, and which column is used as the client delivery date
 * Delete this file (or set $DEBUG_ENABLED = false) once the report is stable.
 */
include ('dbc.php');
page_protect();

$DEBUG_ENABLED = true;
if (!$DEBUG_ENABLED || (int) (isset($_SESSION['user_level']) ? $_SESSION['user_level'] : 0) === 5) {
    header('HTTP/1.1 403 Forbidden');
    exit('Debug page disabled.');
}

function h($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }
function ok($b) { return $b ? '<b style="color:#080;">OK</b>' : '<b style="color:#c00;">MISSING</b>'; }

function esc($v) { global $db; return $db->real_escape_string((string) $v); }

/* run a query, return rows + timing + error (never fatal) */
function dbg_run($sql) {
    global $db;
    $t = microtime(true);
    $res = $db->query($sql);
    $out = array('rows' => array(), 'ms' => round((microtime(true) - $t) * 1000, 2), 'err' => '', 'sql' => $sql);
    if ($res === false) {
        $out['err'] = $db->error;
    } else {
        while ($r = $res->fetch_assoc()) {
            $out['rows'][] = $r;
        }
    }
    return $out;
}

/* print query info + result table */
function dbg_show($title, $r, $max = 50) {
    echo '<h3>' . h($title) . '</h3>';
    echo '<p>' . ($r['err'] === '' ? ok(true) : '<b style="color:#c00;">' . h($r['err']) . '</b>')
        . ' &middot; ' . count($r['rows']) . ' row(s) &middot; ' . h($r['ms']) . ' ms<br><code>' . h($r['sql']) . '</code></p>';
    if (!$r['rows']) {
        echo '<p><i>No rows.</i></p>';
        return;
    }
    echo '<table><tr>';
    foreach (array_keys($r['rows'][0]) as $c) {
        echo '<th>' . h($c) . '</th>';
    }
    echo '</tr>';
    foreach (array_slice($r['rows'], 0, $max) as $row) {
        echo '<tr>';
        foreach ($row as $v) {
            echo '<td>' . h($v) . '</td>';
        }
        echo '</tr>';
    }
    echo '</table>';
    if (count($r['rows']) > $max) {
        echo '<p><i>(first ' . $max . ' rows shown)</i></p>';
    }
}

/* table => columns the report relies on */
$required = array(
    'inw_conversion_dtl' => array('id', 'cust_id', 'book_name', 'book_short_name', 'recv_dt', 'due_dt', 'fig', 'tab', 'manuscript_count', 'stage', 'status',
        'created_dt', 'department_id', 'highpriority', 'graphics', 'fig_complete_status', 'digital_type', 'book_work_type', 'page_count'),
    'inw_conversion_revisions_dtl' => array('r_id', 'b_id', 'received_date', 'due_date'),
    'inw_conversion_revisions_dtl (optional)' => array('revision_count', 'correction_pages'),
    'inw_conversion_project_dtl' => array('id', 'b_id', 'cust_id', 'chapter_title', 'recv_dt', 'due_dt', 'manuscript_count', 'stage', 'status', 'allocate_to', 'department_id', 'created_dt'),
    'inw_conversion_service_dtl' => array('id', 'b_id', 'status'),
    'inw_conversion_project_transactions' => array('id', 'project_id', 'dept', 'stage', 'current_status', 'process_user', 'completion_status', 'comments', 'created_dt'),
    'inw_otp_dispatch_history' => array('idh_id', 'cust_id', 'j_id', 'a_id', 'stage', 'dispatch_type', 'sent_date'),
    'adm_customer_master' => array('id', 'cust_id', 'cust_name', 'font_color'),
    'adm_dept_master' => array('dept_name', 'dept_code', 'parent_id'),
    'users' => array('id', 'full_name', 'approved', 'department_id'),
    'assigned_article_user' => array('aau_id', 'user_id', 'a_date', 'article_ids'),
);

$files = array('dbc.php', 'includes/paginate.php', 'includes/header.php', 'includes/left_sidebar.php', 'includes/footer.php',
    'conversion_master_status_report.php', 'download-excel-wip-report.php', 'download-excel-dp-report.php',
    'job_card.php', 'pending-list.php', 'assigned-work-list.php', 'project_time.php');

$queries = array(
    'WIP (conversion)' => "SELECT c.cust_name, wd.*, r.received_date, r.due_date FROM `inw_conversion_dtl` as wd LEFT JOIN inw_conversion_revisions_dtl as r ON (wd.`id` = r.`b_id` AND r.r_id = (SELECT MAX(r2.r_id) FROM inw_conversion_revisions_dtl as r2 WHERE r2.b_id = wd.`id`)), adm_customer_master as c WHERE wd.cust_id = c.id AND wd.status NOT IN ('Client_Delivery','Client Review','Completed') LIMIT 1",
    'Chapters / services counts' => "SELECT (SELECT COUNT(id) FROM inw_conversion_project_dtl WHERE b_id = wd.id AND status IN ('Client_Delivery','Delivery')) AS ccount, (SELECT COUNT(id) FROM inw_conversion_service_dtl WHERE b_id = wd.id AND status IN ('Client_Delivery','Delivery')) AS sccount FROM inw_conversion_dtl wd LIMIT 1",
    'Allocation (chapters -> users)' => "SELECT GROUP_CONCAT(DISTINCT u.full_name SEPARATOR ', ') AS names FROM inw_conversion_project_dtl chx JOIN users u ON u.id = chx.allocate_to LIMIT 1",
    'Schedules - FP' => "SELECT wd.id, wd.cust_id, wd.status, wd.due_dt, wd.recv_dt, wd.book_short_name, wd.stage FROM inw_conversion_dtl as wd WHERE wd.stage='FP' LIMIT 1",
    'Schedules - REV/FIN' => "SELECT wd.id, r.due_date, r.received_date FROM inw_conversion_dtl as wd LEFT JOIN inw_conversion_revisions_dtl as r ON wd.id = r.b_id WHERE wd.stage LIKE 'REV%' GROUP BY r.b_id LIMIT 1",
    'Transactions (via chapters)' => "SELECT u.full_name, t.current_status, t.id FROM inw_conversion_project_transactions t JOIN users u ON u.id = t.process_user JOIN inw_conversion_project_dtl ch ON ch.id = t.project_id LIMIT 1",
    'Dispatch history' => "SELECT wd.idh_id, wd.stage, wd.sent_date, c.cust_name FROM inw_otp_dispatch_history wd JOIN adm_customer_master c ON wd.cust_id = c.id LIMIT 1",
    'CE/PE allotment' => "SELECT COUNT(aau_id) AS total_count FROM assigned_article_user LIMIT 1",
);

$dbname = '';
$r = $db->query('SELECT DATABASE() AS d');
if ($r && ($row = $r->fetch_assoc())) {
    $dbname = $row['d'];
}

/* ---- filters for sections 4 and 5 (same rules as the report) ---- */
$flt = array('cust_id' => 'all', 'date_wise' => 'm', 'from_dt' => date('01-m-Y'), 'to_dt' => date('d-m-Y'), 'mfrom_dt' => date('m-Y'), 'yfrom_dt' => date('Y'));
foreach ($flt as $k => $d) {
    $flt[$k] = (isset($_GET[$k]) && !is_array($_GET[$k]) && $_GET[$k] !== '') ? trim($_GET[$k]) : $d;
}
$cust_sql = ($flt['cust_id'] !== 'all') ? " AND wd.cust_id = '" . esc($flt['cust_id']) . "' " : '';
$search = '';       // dispatch history (wd.sent_date) - Delivery Performance / journal consolidated
$search_book = '';  // book table (wd.created_dt)      - consolidated on the WIP page
if ($flt['date_wise'] == 'd') {
    $d1 = date('Y-m-d', strtotime($flt['from_dt']));
    $d2 = date('Y-m-d', strtotime($flt['to_dt']));
    $search = "AND DATE_FORMAT(wd.sent_date,'%Y-%m-%d') >='$d1' AND DATE_FORMAT(wd.sent_date,'%Y-%m-%d')<='$d2'";
    $search_book = "AND wd.created_dt >='$d1 00:00:00' AND wd.created_dt<='$d2 23:59:59'";
} elseif ($flt['date_wise'] == 'm') {
    $search = "AND DATE_FORMAT(wd.sent_date,'%m-%Y')='" . esc($flt['mfrom_dt']) . "'";
    $search_book = "AND DATE_FORMAT(wd.created_dt,'%m-%Y')='" . esc($flt['mfrom_dt']) . "'";
} elseif ($flt['date_wise'] == 'y') {
    $search = "AND DATE_FORMAT(wd.sent_date,'%Y')='" . esc($flt['yfrom_dt']) . "'";
    $search_book = "AND DATE_FORMAT(wd.created_dt,'%Y')='" . esc($flt['yfrom_dt']) . "'";
} else {
    $search = "AND wd.sent_date >='2020-03-01'";
    $search_book = "AND wd.created_dt >='2020-03-01'";
}
$custs = dbg_run("SELECT id, cust_name FROM adm_customer_master ORDER BY cust_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Debug - Master Status Report</title>
<style>
    body { font: 13px/1.5 Arial, sans-serif; margin: 20px; color: #222; }
    h2 { margin-top: 28px; border-bottom: 1px solid #ccc; }
    table { border-collapse: collapse; margin-bottom: 10px; }
    td, th { border: 1px solid #ccc; padding: 3px 8px; text-align: left; vertical-align: top; }
    .bad { background: #fdd; }
    code { font: 12px monospace; word-break: break-all; }
</style>
</head>
<body>
<h1>Master Status Report &ndash; diagnostics</h1>

<h2>1. Environment</h2>
<table>
    <tr><th>PHP</th><td><?php echo h(PHP_VERSION); ?></td></tr>
    <tr><th>mysqli</th><td><?php echo ok(extension_loaded('mysqli')); ?></td></tr>
    <tr><th>Database</th><td><?php echo h($dbname); ?></td></tr>
    <tr><th>user_level</th><td><?php echo h(isset($_SESSION['user_level']) ? $_SESSION['user_level'] : '(not set)'); ?></td></tr>
    <tr><th>$platform_journal_array</th><td><?php echo (isset($platform_journal_array) && is_array($platform_journal_array)) ? ok(true) . ' (' . count($platform_journal_array) . ' items)' : ok(false) . ' (platform dropdown will be empty)'; ?></td></tr>
    <tr><th>paginate()</th><td><?php echo ok(function_exists('paginate')); ?></td></tr>
</table>
<table>
    <tr><th>File</th><th>Status</th></tr>
    <?php foreach ($files as $f) { $e = file_exists(__DIR__ . '/' . $f); ?>
        <tr class="<?php echo $e ? '' : 'bad'; ?>"><td><?php echo h($f); ?></td><td><?php echo ok($e); ?></td></tr>
    <?php } ?>
</table>

<h2>2. Tables and columns used by the report</h2>
<?php foreach ($required as $table => $cols) {
    $have = array();
    $res = $db->query("SHOW COLUMNS FROM `" . $db->real_escape_string(preg_replace('/ \(optional\)$/', '', $table)) . "`");
    $table_ok = (bool) $res;
    if ($res) {
        while ($c = $res->fetch_assoc()) {
            $have[strtolower($c['Field'])] = true;
        }
    }
    $missing = array();
    foreach ($cols as $c) {
        if (!isset($have[strtolower($c)])) {
            $missing[] = $c;
        }
    } ?>
    <table>
        <tr class="<?php echo (!$table_ok || $missing) ? 'bad' : ''; ?>">
            <th><?php echo h($table); ?></th>
            <td>
                <?php if (!$table_ok) { ?>
                    table <?php echo ok(false); ?> (<?php echo h($db->error); ?>)
                <?php } elseif ($missing) { ?>
                    missing columns: <b style="color:#c00;"><?php echo h(implode(', ', $missing)); ?></b>
                <?php } else { ?>
                    all <?php echo count($cols); ?> columns <?php echo ok(true); ?>
                <?php } ?>
            </td>
        </tr>
    </table>
<?php } ?>

<h2>3. Query dry run (LIMIT 1)</h2>
<table>
    <tr><th>Query</th><th>ms</th><th>Rows</th><th>Result</th></tr>
    <?php foreach ($queries as $label => $sql) {
        $t = microtime(true);
        $res = $db->query($sql);
        $ms = round((microtime(true) - $t) * 1000, 2); ?>
        <tr class="<?php echo $res ? '' : 'bad'; ?>">
            <td><?php echo h($label); ?><br><code><?php echo h($sql); ?></code></td>
            <td><?php echo h($ms); ?></td>
            <td><?php echo $res ? (int) $res->num_rows : '-'; ?></td>
            <td><?php echo $res ? ok(true) : '<b style="color:#c00;">' . h($db->error) . '</b>'; ?></td>
        </tr>
    <?php } ?>
</table>


<h2>4. Consolidated report</h2>
<form method="get" style="margin-bottom:12px;">
    Customer
    <select name="cust_id">
        <option value="all">-All-</option>
        <?php foreach ($custs['rows'] as $c) { ?>
            <option value="<?php echo h($c['id']); ?>" <?php echo ($flt['cust_id'] == $c['id']) ? 'selected' : ''; ?>><?php echo h($c['cust_name']); ?></option>
        <?php } ?>
    </select>
    Duration
    <select name="date_wise">
        <option value="m" <?php echo $flt['date_wise'] == 'm' ? 'selected' : ''; ?>>Month</option>
        <option value="d" <?php echo $flt['date_wise'] == 'd' ? 'selected' : ''; ?>>Day range</option>
        <option value="y" <?php echo $flt['date_wise'] == 'y' ? 'selected' : ''; ?>>Year</option>
        <option value="all" <?php echo $flt['date_wise'] == 'all' ? 'selected' : ''; ?>>All (since 2020-03-01)</option>
    </select>
    Month (mm-yyyy) <input name="mfrom_dt" size="8" value="<?php echo h($flt['mfrom_dt']); ?>">
    From (dd-mm-yyyy) <input name="from_dt" size="10" value="<?php echo h($flt['from_dt']); ?>">
    To <input name="to_dt" size="10" value="<?php echo h($flt['to_dt']); ?>">
    Year <input name="yfrom_dt" size="4" value="<?php echo h($flt['yfrom_dt']); ?>">
    <button type="submit">Run</button>
</form>
<?php
$open_cons = dbg_run("SELECT c.id, c.cust_name,
        COALESCE(SUM(wd.stage = 'FP'), 0) AS FP,
        COALESCE(SUM(wd.stage LIKE 'REV%'), 0) AS REV,
        COALESCE(SUM(wd.stage LIKE '%FIN%'), 0) AS FIN
    FROM adm_customer_master c
    LEFT JOIN inw_conversion_dtl wd ON wd.cust_id = c.id AND wd.status NOT IN ('Client_Delivery','Delivery','Client Review','Completed') $search_book
    WHERE 1 " . (($flt['cust_id'] !== 'all') ? " AND c.id = '" . esc($flt['cust_id']) . "'" : '') . "
    GROUP BY c.id, c.cust_name ORDER BY c.cust_name");
dbg_show('4a. Consolidated - open conversion projects (the table under the WIP report; period = created date)', $open_cons, 200);

$cust_d = ($flt['cust_id'] !== 'all') ? " AND wd.cust_id = '" . esc($flt['cust_id']) . "' " : '';
dbg_show('4b. Consolidated - dispatches by customer and stage (radio "Consolidated-report")', dbg_run("SELECT c.cust_name,
        COALESCE(SUM(wd.stage = 'FP'),0) AS FP, COALESCE(SUM(wd.stage LIKE 'REV%'),0) AS REV, COALESCE(SUM(wd.stage LIKE 'FIN%'),0) AS FIN
    FROM inw_otp_dispatch_history wd JOIN adm_customer_master c ON wd.cust_id = c.id
    WHERE 1 $cust_d $search GROUP BY c.id, c.cust_name ORDER BY c.cust_name"), 200);
?>

<h2>5. Delivery Performance</h2>
<p>Uses the same filters as section 4 (dispatch date = <code>inw_otp_dispatch_history.sent_date</code>).</p>
<?php
$rev_cols_present = array();
$rr = $db->query("SHOW COLUMNS FROM inw_conversion_revisions_dtl");
while ($rr && ($x = $rr->fetch_assoc())) { $rev_cols_present[strtolower($x['Field'])] = true; }
$rev_match = isset($rev_cols_present['revision_count']) ? " AND rv.revision_count = REPLACE(wd.stage, 'REV', '')" : '';
$rv_due = "(SELECT rv.due_date FROM inw_conversion_revisions_dtl rv WHERE rv.b_id = bk.id AND wd.stage LIKE 'REV%'$rev_match ORDER BY rv.r_id DESC LIMIT 1)";
$due_expr = "COALESCE($rv_due, ch.due_dt, bk.due_dt)";
$dp_from = "FROM inw_otp_dispatch_history wd
    JOIN adm_customer_master c ON wd.cust_id = c.id
    LEFT JOIN inw_conversion_project_dtl ch ON ch.id = wd.a_id
    LEFT JOIN inw_conversion_dtl bk ON bk.id = COALESCE(ch.b_id, wd.a_id)
    WHERE 1 $cust_d $search";
dbg_show('5a. Dispatched count per customer', dbg_run("SELECT c.cust_name, COUNT(wd.idh_id) AS sent_count $dp_from GROUP BY c.id, c.cust_name ORDER BY c.cust_name"), 200);
dbg_show('5b. Schedule totals (Ahead / On time / Delay) - feeds the chart', dbg_run("SELECT COUNT(*) AS total,
        COALESCE(SUM(DATE(sentdt) < DATE(due)),0) AS Ahead, COALESCE(SUM(DATE(sentdt) = DATE(due)),0) AS On_time,
        COALESCE(SUM(DATE(sentdt) > DATE(due)),0) AS Delay, COALESCE(SUM(due IS NULL),0) AS no_due_date_found
    FROM (SELECT wd.sent_date AS sentdt, $due_expr AS due $dp_from) x"));
dbg_show('5c. Latest 20 rows of the report table', dbg_run("SELECT wd.idh_id, c.cust_name, bk.book_short_name AS project, ch.chapter_title AS chapter, wd.stage,
        DATE(wd.sent_date) AS dispatched, DATE($due_expr) AS due,
        CASE WHEN $due_expr IS NULL THEN 'no due date' WHEN DATE(wd.sent_date) = DATE($due_expr) THEN 'On Time'
             WHEN DATE(wd.sent_date) > DATE($due_expr) THEN 'Delay' ELSE 'Ahead' END AS schedule
    $dp_from ORDER BY wd.idh_id DESC LIMIT 20"), 20);

echo '<h3>5d. What does inw_otp_dispatch_history.a_id point to?</h3>';
dbg_show('Dispatch history overview (all dates)', dbg_run("SELECT COUNT(*) AS total_rows, MIN(sent_date) AS first_sent, MAX(sent_date) AS last_sent,
        COUNT(DISTINCT dispatch_type) AS dispatch_types, GROUP_CONCAT(DISTINCT dispatch_type SEPARATOR ', ') AS dispatch_type_values
    FROM inw_otp_dispatch_history"));
dbg_show('a_id matches (the report uses chapter id first, then book id)', dbg_run("SELECT COUNT(*) AS rows_total,
        SUM(ch.id IS NOT NULL) AS a_id_is_chapter_id, SUM(ch.id IS NULL AND bk.id IS NOT NULL) AS a_id_is_book_id, SUM(ch.id IS NULL AND bk.id IS NULL) AS no_match
    FROM inw_otp_dispatch_history wd
    LEFT JOIN inw_conversion_project_dtl ch ON ch.id = wd.a_id
    LEFT JOIN inw_conversion_dtl bk ON bk.id = wd.a_id"));
dbg_show('Status values in use (inw_conversion_dtl)', dbg_run("SELECT status, COUNT(*) AS projects FROM inw_conversion_dtl GROUP BY status ORDER BY projects DESC"), 50);
?>

<p>Tip: open the report itself with <code>?debug=1</code> to see the exact SQL, timings and PHP warnings for a real request.</p>
</body>
</html>
