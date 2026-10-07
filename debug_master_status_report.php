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

/* table => columns the report relies on */
$required = array(
    'inw_book_dtl' => array('id', 'cust_id', 'department_id', 'status', 'stage', 'recv_dt', 'due_dt', 'created_dt', 'highpriority',
        'manuscript_count', 'fig_complete_status', 'book_name', 'book_short_name', 'assigned_user_id', 'ce_pe_due_date'),
    'inw_book_revisions_dtl' => array('r_id', 'b_id', 'received_date', 'due_date', 'correction_pages', 'revision_count', 'created_on'),
    'adm_customer_master' => array('id', 'cust_id', 'cust_name', 'font_color'),
    'adm_dept_master' => array('dept_name', 'dept_code', 'parent_id'),
    'inw_transactions' => array('id', 'project_id', 'process_user', 'current_status', 'completion_status', 'comments', 'stage', 'dept', 'created_dt'),
    'users' => array('id', 'full_name', 'approved', 'department_id'),
    'assigned_article_user' => array('aau_id', 'user_id', 'a_date', 'article_ids'),
    /* journal tables still used by Delivery Performance / Consolidated / CE-PE / Detailed reports */
    'inw_dispatch_history' => array('idh_id', 'a_id', 'cust_id', 'j_id', 'stage', 'sent_date'),
    'inw_inward_dtl' => array('id', 'cust_id', 'j_id', 'pub_id', 'stage', 'status', 'recv_dt', 'due_dt', 'created_dt', 'manuscript_count', 'page_count', 'assigned_user_id', 'ce_pe_due_date', 'v_issue_id'),
    'inw_revisions_dtl' => array('inw_id', 'r_id', 'revision_count', 'received_date', 'due_date', 'correction_pages'),
    'adm_journals' => array('j_id', 'j_cust_id', 'j_code', 'j_platform'),
);

$files = array('dbc.php', 'includes/paginate.php', 'includes/header.php', 'includes/left_sidebar.php', 'includes/footer.php',
    'conversion_master_status_report.php', 'download-excel-wip-report.php', 'download-excel-dp-report.php',
    'job_card.php', 'pending-list.php', 'assigned-work-list.php', 'project_time.php');

$queries = array(
    'WIP (books)' => "SELECT c.cust_name, wd.*, r.received_date, r.due_date, r.correction_pages, r.revision_count FROM `inw_book_dtl` as wd LEFT JOIN inw_book_revisions_dtl as r ON (wd.`id` = r.`b_id` AND r.r_id = (SELECT MAX(r2.r_id) FROM inw_book_revisions_dtl as r2 WHERE r2.b_id = wd.`id`)), adm_customer_master as c WHERE wd.cust_id = c.id AND wd.status NOT IN ('Client Review','Completed') LIMIT 1",
    'Schedules - FP (books)' => "SELECT wd.id, wd.cust_id, wd.status, wd.due_dt, wd.recv_dt, wd.book_short_name, wd.stage, wd.department_id FROM inw_book_dtl as wd WHERE wd.stage='FP' LIMIT 1",
    'Schedules - REV/FIN (books)' => "SELECT wd.id, r.due_date, r.received_date FROM inw_book_dtl as wd LEFT JOIN inw_book_revisions_dtl as r ON wd.id = r.b_id WHERE wd.stage LIKE 'REV%' GROUP BY r.b_id LIMIT 1",
    'Transactions per book' => "SELECT u.full_name, t.current_status, t.id FROM inw_transactions as t, users as u WHERE t.process_user = u.id LIMIT 1",
    'Delivery performance' => "SELECT wd.idh_id, c.cust_name, j.j_code FROM inw_dispatch_history as wd, adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id AND wd.j_id = j.j_id LIMIT 1",
    'CE/PE allotment' => "SELECT COUNT(aau_id) AS total_count FROM assigned_article_user LIMIT 1",
);

$dbname = '';
$r = $db->query('SELECT DATABASE() AS d');
if ($r && ($row = $r->fetch_assoc())) {
    $dbname = $row['d'];
}
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
    $res = $db->query("SHOW COLUMNS FROM `" . $db->real_escape_string($table) . "`");
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

<p>Tip: open the report itself with <code>?debug=1</code> to see the exact SQL, timings and PHP warnings for a real request.</p>
</body>
</html>
