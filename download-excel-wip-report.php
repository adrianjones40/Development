<?php
include ('dbc.php');
page_protect();

/* ---------------------------- helpers ---------------------------- */
class XlsEmptyResult {
    public $num_rows = 0;
    public function fetch_assoc() { return null; }
    public function fetch_array() { return null; }
}

function h($v) {
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function esc($v) {
    global $db;
    return $db->real_escape_string((string) $v);
}

/* Run a query; never fatals. Errors go to the PHP error log, an empty result is returned. */
function dbq($sql) {
    global $db;
    $res = $db->query($sql);
    if ($res === false) {
        error_log('download-excel-wip-report SQL error: ' . $db->error . ' | ' . $sql);
        return new XlsEmptyResult();
    }
    return $res;
}

function dbq_row($sql) {
    $r = dbq($sql)->fetch_array();
    return $r ? $r : array();
}

/* Blank instead of 01 Jan 1970 for empty / zero dates. */
function fmt_dt($v) {
    if ($v === null || $v === '' || strpos((string) $v, '0000-00-00') === 0) {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('d M Y', $ts) : '';
}

/* Column names of a table (lower-case => true), so optional columns can be skipped instead of failing. */
function xls_cols($table) {
    static $cache = array();
    if (!isset($cache[$table])) {
        $cache[$table] = array();
        $r = dbq("SHOW COLUMNS FROM `" . $table . "`");
        while ($x = $r->fetch_assoc()) {
            $cache[$table][strtolower($x['Field'])] = true;
        }
    }
    return $cache[$table];
}

/* FP uses the project dates, later stages the latest revision (project dates as fallback). */
function xls_dates($row) {
    $recv = $row['recv_dt'];
    $due = $row['due_dt'];
    if ($row['stage'] != 'FP' && !empty($row['due_date'])) {
        $due = $row['due_date'];
        if (!empty($row['received_date'])) {
            $recv = $row['received_date'];
        }
    }
    return array($recv, $due);
}

/* ------------------- request normalisation ------------------- */
foreach (array('radio', 'cust_id', 'j_id', 'stage_id', 'dep_id', 'dp_platform', 'date_wise', 'from_dt', 'to_dt', 'mfrom_dt', 'yfrom_dt') as $k) {
    if (!isset($_REQUEST[$k]) || is_array($_REQUEST[$k])) {
        $_REQUEST[$k] = '';
    }
}
$is_cust_user = ((int) (isset($_SESSION['user_level']) ? $_SESSION['user_level'] : 0) === 5);
$search1 = $search2 = $search3 = '';
$cccb = '';
$conv_cols = xls_cols('inw_conversion_dtl');
$rev_cols = xls_cols('inw_conversion_revisions_dtl');

/* ------------------------------ filters (same rules as the on-screen WIP report) ------------------------------ */
if ($_REQUEST['cust_id'] != "" && $_REQUEST['cust_id'] != 'all') {
    $cccb .= "and wd.cust_id='" . esc($_REQUEST['cust_id']) . "' ";
}
if ($_REQUEST['stage_id'] != "" && $_REQUEST['stage_id'] != 'all') {
    $search2 .= "and wd.stage like '%" . esc($_REQUEST['stage_id']) . "%' ";
}
if ($_REQUEST['dep_id'] != '' && isset($conv_cols['department_id'])) {
    $cccb .= "and wd.department_id= '" . esc($_REQUEST['dep_id']) . "' ";
}

/* Status filter: Query / Hold are only included when ticked. */
$inpt_status = (isset($_REQUEST['inpt_status']) && is_array($_REQUEST['inpt_status'])) ? array_values($_REQUEST['inpt_status']) : array();
$excluded_status = array('Client_Delivery', 'Delivery', 'Client Review', 'Completed', 'Query', 'Hold');
foreach (array('Query', 'Hold') as $st) {
    if (in_array($st, $inpt_status, true)) {
        $excluded_status = array_diff($excluded_status, array($st));
    }
}
$status_qry = " AND wd.status NOT IN ('" . implode("','", $excluded_status) . "') ";

if ($_REQUEST['from_dt'] != "" && $_REQUEST['date_wise'] == "d") {
    $frm = date('Y-m-d', strtotime($_REQUEST['from_dt'])) . " 00:00:00";
    $to = date('Y-m-d', strtotime($_REQUEST['to_dt'])) . " 23:59:59";
    $search3 .= "and ((wd.recv_dt >='" . $frm . "' AND wd.recv_dt<='" . $to . "') or (r.received_date >='" . $frm . "' AND r.received_date<='" . $to . "'))";
}
if ($_REQUEST['date_wise'] == "m") {
    $frm = esc($_REQUEST['mfrom_dt']);
    $search3 .= "and ((DATE_FORMAT(wd.recv_dt,'%m-%Y')='" . $frm . "') or (DATE_FORMAT(r.received_date,'%m-%Y')='" . $frm . "'))";
}
if ($_REQUEST['date_wise'] == "y") {
    $frm = esc($_REQUEST['yfrom_dt']);
    $search3 .= "and ((DATE_FORMAT(wd.recv_dt,'%Y')='" . $frm . "') or (DATE_FORMAT(r.received_date,'%Y')='" . $frm . "'))";
}
if ($_REQUEST['date_wise'] != "y" && $_REQUEST['date_wise'] != "m" && $_REQUEST['date_wise'] != "d") {
    $search3 .= "AND wd.created_dt >='2020-03-01'";
}

/* ------------------------------ query (conversion projects) ------------------------------ */
$rev_extra = '';
foreach (array('revision_count', 'correction_pages') as $rc) {
    if (isset($rev_cols[$rc])) {
        $rev_extra .= ", r.$rc";
    }
}
$wip_from = "FROM `inw_conversion_dtl` as wd LEFT JOIN inw_conversion_revisions_dtl as r ON (wd.`id` = r.`b_id` AND r.r_id = (SELECT MAX(r2.r_id) FROM inw_conversion_revisions_dtl as r2 WHERE r2.b_id = wd.`id`)), adm_customer_master as c WHERE wd.cust_id = c.id ";
if ($is_cust_user) {
    $wip_scope = " AND (wd.cust_id = '" . esc($_SESSION['cust_id']) . "' OR FIND_IN_SET(wd.cust_id,'" . esc($_SESSION['ocust_id']) . "')) AND wd.status NOT IN ('Client_Delivery','Client Review','Completed') ";
} else {
    $wip_scope = " AND wd.status NOT IN ('Client_Delivery','Client Review','Completed') ";
}
$wip_where = $wip_scope . " $cccb $search1 $search2 $search3 " . $status_qry . " ";
$order_by = isset($conv_cols['highpriority']) ? "ORDER BY FIELD(wd.highpriority, 1) desc, wd.due_dt asc" : "ORDER BY wd.due_dt asc";
$query = "SELECT c.cust_name, wd.*, r.received_date, r.due_date" . $rev_extra . ",
    (SELECT COUNT(id) FROM inw_conversion_project_dtl WHERE b_id = wd.id AND status IN ('Client_Delivery','Delivery')) AS ccount,
    (SELECT COUNT(id) FROM inw_conversion_project_dtl WHERE b_id = wd.id AND status NOT IN ('Client_Delivery','Delivery')) AS pcount,
    (SELECT COUNT(id) FROM inw_conversion_service_dtl WHERE b_id = wd.id AND status IN ('Client_Delivery','Delivery')) AS sccount,
    (SELECT COUNT(id) FROM inw_conversion_service_dtl WHERE b_id = wd.id AND status NOT IN ('Client_Delivery','Delivery')) AS spcount " . $wip_from . $wip_where . " " . $order_by;

$articles = dbq($query);
$total_pages = $articles->num_rows;
$show_alloc = (($_REQUEST['dep_id'] == '1' || $_REQUEST['dep_id'] == '2') && isset($conv_cols['assigned_user_id']));

/* ------------------------------ output (.xls) ------------------------------
   Headers are sent only now, after the queries, so a failure cannot corrupt the download.
   File name has no ':' (invalid on Windows) and the HTML table is declared UTF-8. */
while (ob_get_level() > 0) {
    ob_end_clean();
}
header("Content-Type: application/vnd.ms-excel; charset=utf-8");
header("Content-Disposition: attachment; filename=\"wip-report-" . date('Y-m-d-H-i') . ".xls\"");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: public");
?>
<html xmlns:x="urn:schemas-microsoft-com:office:excel">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<!--[if gte mso 9]><xml><x:ExcelWorkbook><x:ExcelWorksheets><x:ExcelWorksheet><x:Name>WIP Report</x:Name><x:WorksheetOptions><x:DisplayGridlines/></x:WorksheetOptions></x:ExcelWorksheet></x:ExcelWorksheets></x:ExcelWorkbook></xml><![endif]-->
</head>
<body>
<table border="1" cellspacing="0" cellpadding="3">
    <tr><td colspan="14"><b>WIP Report - Total count: <?php echo (int) $total_pages; ?></b></td></tr>
    <thead>
        <tr style="background-color:#4E9AEC;color:#ffffff;">
            <th>Client</th>
            <th>Project Name</th>
            <th>Work Type</th>
            <th>Stage</th>
            <th>Page Count</th>
            <th>Fig Count</th>
            <th>Table Count</th>
            <th>Received Date</th>
            <th>Due Date</th>
            <?php if ($show_alloc) { ?><th>Alloted to</th><th>Alloted Due date</th><?php } ?>
            <th>#Chapters Done</th>
            <th>#Chapters Pending</th>
            <th>#Services Done</th>
            <th>#Services Pending</th>
            <th>Status</th>
        </tr>
    </thead>
    <tbody>
<?php
while ($row_history = $articles->fetch_assoc()) {
    list($recv_dt, $due_dt) = xls_dates($row_history);

    $trans = dbq("SELECT u.full_name,t.current_status FROM `inw_conversion_project_transactions` as t,users as u WHERE t.process_user = u.id AND t.`project_id` = '" . (int) $row_history['id'] . "' AND t.created_dt > '2020-03-01'  AND t.current_status NOT IN ('Completed','Take Over') AND t.stage = '" . esc($row_history['stage']) . "' AND (t.id=(select id from `inw_conversion_project_transactions` where dept!=9 and `project_id` = '" . (int) $row_history['id'] . "' ORDER BY `id` DESC limit 1))  ORDER BY t.`id` DESC limit 1")->fetch_assoc();
    $assign_comments = dbq("select comments from inw_conversion_project_transactions where project_id='" . (int) $row_history['id'] . "' and dept='14' and completion_status=6 order by id desc limit 1");
    $res_ac = $assign_comments->fetch_assoc();
    $assign_cepe = $show_alloc ? dbq_row("select full_name from users where id='" . (int) $row_history['assigned_user_id'] . "'") : array();

    // row colour (CSS classes do not exist in Excel, so use inline colours)
    $bg = '';
    $due_ts = $due_dt ? strtotime($due_dt) : false;
    if (isset($row_history['highpriority']) && $row_history['highpriority'] == 1) {
        $bg = '#d9edf7';
    } elseif ($row_history['status'] == 'Query' || $row_history['status'] == 'Hold') {
        $bg = '#d3d3d4';
    } elseif ($due_ts && strtotime(date('Y-m-d')) > $due_ts) {
        $bg = '#f2dede';
    }

    // status cell as plain text (HTML labels are meaningless in Excel)
    $status_txt = $row_history['status'];
    if ($trans) {
        $status_txt .= ' | ' . $trans['full_name'] . ' | ' . $trans['current_status'];
    }
    if ($res_ac) {
        $status_txt .= ' | ' . ($res_ac['comments'] != '' ? $res_ac['comments'] : 'No Comments');
    }
    ?>
        <tr<?php echo $bg ? ' style="background-color:' . $bg . ';"' : ''; ?>>
            <td><?php echo h($row_history['cust_name']); ?></td>
            <td><?php echo h($row_history['book_short_name']); ?></td>
            <td><?php echo h(isset($row_history['digital_type']) ? $row_history['digital_type'] : ''); ?></td>
            <td><?php echo h($row_history['stage']); ?></td>
            <td><?php echo h(isset($row_history['manuscript_count']) ? $row_history['manuscript_count'] : ''); ?></td>
            <td><?php echo h(isset($row_history['fig']) ? $row_history['fig'] : ''); ?></td>
            <td><?php echo h(isset($row_history['tab']) ? $row_history['tab'] : ''); ?></td>
            <td><?php echo h(fmt_dt($recv_dt)); ?></td>
            <td><?php echo h(fmt_dt($due_dt)); ?></td>
            <?php if ($show_alloc) { ?>
                <td><?php echo h(isset($assign_cepe['full_name']) ? $assign_cepe['full_name'] : ''); ?></td>
                <td><?php echo h(fmt_dt(isset($row_history['ce_pe_due_date']) ? $row_history['ce_pe_due_date'] : '')); ?></td>
            <?php } ?>
            <td><?php echo (int) $row_history['ccount']; ?></td>
            <td><?php echo (int) $row_history['pcount']; ?></td>
            <td><?php echo (int) $row_history['sccount']; ?></td>
            <td><?php echo (int) $row_history['spcount']; ?></td>
            <td><?php echo h($status_txt); ?></td>
        </tr>
<?php
}
?>
    </tbody>
</table>
</body>
</html>
