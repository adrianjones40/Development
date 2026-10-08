<?php
include ('dbc.php');
page_protect();
include ('includes/paginate.php');

/* ------------------------------------------------------------------
 * Debug mode: open this page with ?debug=1 to get a panel at the bottom
 * (request, filters, every SQL query with timing/rows/errors, PHP warnings).
 * Set MSR_DEBUG_ALLOWED to false to switch it off completely in production.
 * Customer users (user_level 5) can never see it.
 * ------------------------------------------------------------------ */
define('MSR_DEBUG_ALLOWED', true);
$msr_t0 = microtime(true);
$msr_log = array('queries' => array(), 'errors' => array());
$msr_debug = MSR_DEBUG_ALLOWED && !empty($_GET['debug']) && ((int) (isset($_SESSION['user_level']) ? $_SESSION['user_level'] : 0) !== 5);
if ($msr_debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', '0'); // captured into the panel instead of breaking the markup
    set_error_handler(function ($no, $str, $file, $line) {
        $GLOBALS['msr_log']['errors'][] = array('no' => $no, 'msg' => $str, 'file' => basename($file), 'line' => $line);
        return true;
    });
}

/* ---------------------------- helpers ---------------------------- */
class MsrEmptyResult {
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

/* Run a query; never fatals. On error it is logged (and shown in debug mode) and an empty result is returned. */
function dbq($sql) {
    global $db;
    $t = microtime(true);
    $res = $db->query($sql);
    $ms = round((microtime(true) - $t) * 1000, 2);
    $err = ($res === false) ? $db->error : '';
    if ($err !== '') {
        error_log('master_status_report SQL error: ' . $err . ' | ' . $sql);
    }
    if (!empty($GLOBALS['msr_debug'])) {
        $GLOBALS['msr_log']['queries'][] = array(
            'sql' => $sql, 'ms' => $ms, 'error' => $err,
            'rows' => (is_object($res) && isset($res->num_rows)) ? $res->num_rows : 0
        );
    }
    return ($res === false) ? new MsrEmptyResult() : $res;
}

/* Single row as array (empty array when nothing found). */
function dbq_row($sql) {
    $r = dbq($sql)->fetch_array();
    return $r ? $r : array();
}

/* Date for display; blank instead of 01 Jan 1970 for empty / zero dates. */
function fmt_dt($v) {
    if ($v === null || $v === '' || strpos((string) $v, '0000-00-00') === 0) {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('d M Y', $ts) : '';
}

/* Query string (urlencoded) built from the current request, used for pagination / excel links. */
function msr_qs($keys) {
    $p = array();
    foreach ($keys as $k) {
        $p[$k] = isset($_REQUEST[$k]) ? $_REQUEST[$k] : '';
    }
    if (!empty($GLOBALS['inpt_status'])) {
        $p['inpt_status'] = $GLOBALS['inpt_status'];
    }
    if (!empty($GLOBALS['msr_debug'])) {
        $p['debug'] = 1;
    }
    return http_build_query($p);
}

function msr_debug_panel() {
    global $msr_debug, $msr_log, $msr_t0, $ccc, $cccb, $search, $search1, $search2, $search3, $search4, $search_cd, $search_cm, $search_cy;
    if (!$msr_debug) {
        return;
    }
    $total_ms = round((microtime(true) - $msr_t0) * 1000, 1);
    $q_ms = 0;
    $q_err = 0;
    foreach ($msr_log['queries'] as $q) {
        $q_ms += $q['ms'];
        if ($q['error'] !== '') {
            $q_err++;
        }
    }
    $filters = array('ccc (journal)' => $ccc, 'cccb (book)' => $cccb, 'search' => $search, 'search1' => $search1,
        'search2' => $search2, 'search3' => $search3, 'search4' => $search4, 'search_cd' => $search_cd,
        'search_cm' => $search_cm, 'search_cy' => $search_cy,
        'inpt_status_qry' => isset($_SESSION['inpt_status_qry']) ? $_SESSION['inpt_status_qry'] : '');
    $sess = array();
    foreach (array('user_level', 'cust_id', 'ocust_id', 'inpt_status') as $k) {
        $sess[$k] = isset($_SESSION[$k]) ? $_SESSION[$k] : '(not set)';
    }
    ?>
    <div id="msr-debug" style="clear:both;margin:20px;padding:10px;border:2px solid #c00;background:#fffbe6;font:12px/1.4 monospace;color:#222;">
        <h3 style="margin:0 0 8px;color:#c00;">Debug &ndash; Master Status Report</h3>
        <p>
            PHP <?php echo h(PHP_VERSION); ?> |
            page <?php echo h($total_ms); ?> ms |
            <?php echo count($msr_log['queries']); ?> queries (<?php echo h(round($q_ms, 1)); ?> ms) |
            <b style="color:<?php echo $q_err ? '#c00' : '#080'; ?>;"><?php echo (int) $q_err; ?> SQL errors</b> |
            <b style="color:<?php echo count($msr_log['errors']) ? '#c00' : '#080'; ?>;"><?php echo count($msr_log['errors']); ?> PHP warnings</b> |
            peak memory <?php echo h(round(memory_get_peak_usage(true) / 1048576, 1)); ?> MB
        </p>
        <details open><summary><b>Request</b></summary><pre><?php echo h(print_r($_REQUEST, true)); ?></pre></details>
        <details open><summary><b>Session (filter related)</b></summary><pre><?php echo h(print_r($sess, true)); ?></pre></details>
        <details open><summary><b>Built filters</b></summary><pre><?php echo h(print_r($filters, true)); ?></pre></details>
        <details open><summary><b>PHP warnings / notices (<?php echo count($msr_log['errors']); ?>)</b></summary>
            <pre><?php foreach ($msr_log['errors'] as $e) { echo h('[' . $e['no'] . '] ' . $e['msg'] . ' in ' . $e['file'] . ':' . $e['line']) . "\n"; } ?></pre>
        </details>
        <details open><summary><b>SQL queries (<?php echo count($msr_log['queries']); ?>)</b></summary>
            <table border="1" cellpadding="3" cellspacing="0" style="border-collapse:collapse;width:100%;background:#fff;">
                <tr><th>#</th><th>ms</th><th>rows</th><th>error</th><th>SQL</th></tr>
                <?php foreach ($msr_log['queries'] as $n => $q) { ?>
                    <tr style="<?php echo $q['error'] !== '' ? 'background:#fdd;' : ($q['ms'] > 200 ? 'background:#ffe0b3;' : ''); ?>">
                        <td><?php echo $n + 1; ?></td>
                        <td><?php echo h($q['ms']); ?></td>
                        <td><?php echo (int) $q['rows']; ?></td>
                        <td style="color:#c00;"><?php echo h($q['error']); ?></td>
                        <td style="word-break:break-all;"><?php echo h($q['sql']); ?></td>
                    </tr>
                <?php } ?>
            </table>
        </details>
        <p>Schema check: <a href="debug_master_status_report.php" target="_blank">debug_master_status_report.php</a></p>
    </div>
    <?php
}

/* ------------------- request normalisation (no more undefined-index notices) ------------------- */
$self = basename($_SERVER['PHP_SELF']);
foreach (array('radio', 'cust_id', 'j_id', 'stage_id', 'dep_id', 'dp_platform', 'date_wise', 'from_dt', 'to_dt',
    'mfrom_dt', 'yfrom_dt', 'yto_dt', 'cfrom_dt', 'cto_dt', 'sdf_dt', 'start', 'radio_check') as $k) {
    if (!isset($_REQUEST[$k]) || is_array($_REQUEST[$k])) {
        $_REQUEST[$k] = '';
    }
}
$radio_check = $_REQUEST['radio_check'];
$is_cust_user = ((int) (isset($_SESSION['user_level']) ? $_SESSION['user_level'] : 0) === 5);

$search = $search1 = $search2 = $search3 = $search4 = '';
$search_cd = $search_cm = $search_cy = $searchfp = '';
$ccc = '';   // filters for journal tables (adm_journals aliased as j)
$cccb = '';  // filters for book tables (inw_book_dtl aliased as wd)
$otherParams = '';
$i = $j = $k = $l = 0;
$kg = $jg = $lg = 0;
$tfp_id_count = $tfev_id_count = $tfin_id_count = 0;
$filePathKeys = array('radio', 'cust_id', 'j_id', 'stage_id', 'dep_id', 'dp_platform', 'from_dt', 'to_dt', 'mfrom_dt', 'yfrom_dt', 'date_wise');

/* ------------------------------ filters ------------------------------ */
if ($_REQUEST['cust_id'] != "" && $_REQUEST['cust_id'] != 'all') {
    $f = "and wd.cust_id='" . esc($_REQUEST['cust_id']) . "' ";
    $ccc .= $f;
    $cccb .= $f;
}
if ($_REQUEST['j_id'] != "" && $_REQUEST['j_id'] != 'all') {
    $ccc .= "and j.j_id='" . esc($_REQUEST['j_id']) . "' ";
}
if ($_REQUEST['stage_id'] != "" && $_REQUEST['stage_id'] != 'all') {
    $search2 .= "and wd.stage like '%" . esc($_REQUEST['stage_id']) . "%' ";
}
if ($_REQUEST['dep_id'] != '') {
    $ccc .= "and department_id= '" . esc($_REQUEST['dep_id']) . "' ";
    $cccb .= "and wd.department_id= '" . esc($_REQUEST['dep_id']) . "' ";
}
if ($_REQUEST['dp_platform'] != "") {
    $ccc .= "and j.j_platform='" . esc($_REQUEST['dp_platform']) . "' ";
}

/* Status filter (Query / Hold checkboxes). Read from the request every time so paging keeps it
   and un-ticking both boxes really resets it. */
$inpt_status = (isset($_REQUEST['inpt_status']) && is_array($_REQUEST['inpt_status'])) ? array_values($_REQUEST['inpt_status']) : array();
$excluded_status = array('Delivery', 'Client Review', 'Completed', 'Query', 'Hold');
foreach (array('Query', 'Hold') as $st) {
    if (in_array($st, $inpt_status, true)) {
        $excluded_status = array_diff($excluded_status, array($st));
    }
}
$_SESSION['inpt_status'] = $inpt_status;
$_SESSION['inpt_status_qry'] = " AND wd.status NOT IN ('" . implode("','", $excluded_status) . "') ";

if ($_REQUEST['radio'] == 'ce') {
    if ($_REQUEST['cfrom_dt'] != "") {
        $frm = date('Y-m-d', strtotime($_REQUEST['cfrom_dt']));
        $to = date('Y-m-d', strtotime($_REQUEST['cto_dt']));
        $search .= "and DATE_FORMAT(sent_date,'%Y-%m-%d') >='" . $frm . "' AND DATE_FORMAT(sent_date,'%Y-%m-%d')<='" . $to . "'";
    }
}
if ($_REQUEST['radio'] == 'dp' || $_REQUEST['radio'] == 'cr') {
    if ($_REQUEST['from_dt'] != "" && $_REQUEST['date_wise'] == "d") {
        $frm = date('Y-m-d', strtotime($_REQUEST['from_dt']));
        $to = date('Y-m-d', strtotime($_REQUEST['to_dt']));
        $search .= "and DATE_FORMAT(wd.sent_date,'%Y-%m-%d') >='" . $frm . "' AND DATE_FORMAT(wd.sent_date,'%Y-%m-%d')<='" . $to . "'";
    }
    if ($_REQUEST['date_wise'] == "m") {
        $search .= "and DATE_FORMAT(wd.sent_date,'%m-%Y')='" . esc($_REQUEST['mfrom_dt']) . "'";
    }
    if ($_REQUEST['date_wise'] == "y") {
        $search .= "and DATE_FORMAT(wd.sent_date,'%Y')='" . esc($_REQUEST['yfrom_dt']) . "'";
    }
    if ($_REQUEST['date_wise'] != "y" && $_REQUEST['date_wise'] != "m" && $_REQUEST['date_wise'] != "d") {
        $search3 .= "AND wd.sent_date >='2020-03-01'";
        $search4 .= "AND wd.sent_date >='2020-03-01'";
    }
} elseif ($_REQUEST['radio'] == 'sfd') {
    if ($_REQUEST['sdf_dt'] != "") {
        $frm = date('Y-m-d', strtotime($_REQUEST['sdf_dt']));
        $search .= "and DATE_FORMAT(wd.due_dt,'%Y-%m-%d')='" . $frm . "'";
    }
} else {
    if ($_REQUEST['from_dt'] != "" && $_REQUEST['date_wise'] == "d") {
        $frm = date('Y-m-d', strtotime($_REQUEST['from_dt'])) . " 00:00:00";
        $to = date('Y-m-d', strtotime($_REQUEST['to_dt'])) . " 23:59:59";
        $search .= "and wd.recv_dt >='" . $frm . "' AND wd.recv_dt<='" . $to . "'";
        $search3 .= "and ((wd.recv_dt >='" . $frm . "' AND wd.recv_dt<='" . $to . "') or (r.received_date >='" . $frm . "' AND r.received_date<='" . $to . "'))";
        $search4 .= "and ((wd.due_dt >='" . $frm . "' AND wd.due_dt<='" . $to . "') or (r.due_date >='" . $frm . "' AND r.due_date<='" . $to . "'))";
        $search_cd .= "and (created_dt >='" . $frm . "' AND created_dt<='" . $to . "')";
    }
    if ($_REQUEST['date_wise'] == "m") {
        $frm = esc($_REQUEST['mfrom_dt']);
        $search .= "and DATE_FORMAT(wd.recv_dt,'%m-%Y')='" . $frm . "'";
        $search3 .= "and ((DATE_FORMAT(wd.recv_dt,'%m-%Y')='" . $frm . "') or (DATE_FORMAT(r.received_date,'%m-%Y')='" . $frm . "'))";
        $search4 .= "and ((DATE_FORMAT(wd.recv_dt,'%m-%Y')='" . $frm . "') or (DATE_FORMAT(r.due_date,'%m-%Y')='" . $frm . "'))";
        $search_cm .= "and (DATE_FORMAT(created_dt,'%m-%Y')='" . $frm . "')";
    }
    if ($_REQUEST['date_wise'] == "y") {
        $frm = esc($_REQUEST['yfrom_dt']);
        $search .= "and DATE_FORMAT(wd.recv_dt,'%Y')='" . $frm . "'";
        $search_cy .= "and (DATE_FORMAT(created_dt,'%Y')='" . $frm . "')";
        $search3 .= "and ((DATE_FORMAT(wd.recv_dt,'%Y')='" . $frm . "') or (DATE_FORMAT(r.received_date,'%Y')='" . $frm . "'))";
        $search4 .= "and ((DATE_FORMAT(wd.due_dt,'%Y')='" . $frm . "') or (DATE_FORMAT(r.due_date,'%Y')='" . $frm . "'))";
    }
    if ($_REQUEST['date_wise'] != "y" && $_REQUEST['date_wise'] != "m" && $_REQUEST['date_wise'] != "d") {
        $search3 .= "AND wd.created_dt >='2020-03-01'";
        $search4 .= "AND wd.created_dt >='2020-03-01'";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1" />
        <meta charset="utf-8" />
        <title>Report Information - Workflow</title>
        <meta name="description" content="3 styles with inline editable feature" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0" />

        <!-- bootstrap & fontawesome -->
        <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
        <link rel="stylesheet" href="assets/font-awesome/4.5.0/css/font-awesome.min.css" />

        <!-- page specific plugin styles -->
        <link rel="stylesheet" href="assets/css/select2.min.css" />
        <link rel="stylesheet" href="assets/css/bootstrap-datepicker3.min.css" />

        <!-- text fonts -->
        <link rel="stylesheet" href="assets/css/fonts.googleapis.com.css" />

        <!-- ace styles -->
        <link rel="stylesheet" href="assets/css/ace.min.css" class="ace-main-stylesheet" id="main-ace-style" />

        <link rel="stylesheet" href="assets/css/ace-skins.min.css" />
        <link rel="stylesheet" href="assets/css/ace-rtl.min.css" />

        <script src="assets/js/ace-extra.min.js"></script>


        <style>
            /* The radio-container */
            .radio-container {

                position: relative;
                padding-left: 35px;
                padding-right: 10px;
                margin-bottom: 12px;
                cursor: pointer;
                font-size: 15px;
                -webkit-user-select: none;
                -moz-user-select: none;
                -ms-user-select: none;
                user-select: none;
            }

            /* Hide the browser's default radio button */
            .radio-container input {
                position: absolute;
                opacity: 0;
                cursor: pointer;
            }

            /* Create a custom radio button */
            .checkmark {
                position: absolute;
                top: 0;
                left: 0;
                height: 20px;
                width: 20px;
                background-color: #dde6dd;
                border-radius: 50%;
                border: 1px solid green;
            }

            /* On mouse-over, add a grey background color */
            .radio-container:hover input ~ .checkmark {
                background-color: #ccc;
            }

            /* When the radio button is checked, add a blue background */
            .radio-container input:checked ~ .checkmark {
                background-color:rgba(40, 146, 10, 0.73);
            }

            /* Create the indicator (the dot/circle - hidden when not checked) */
            .checkmark:after {
                content: "";
                position: absolute;
                display: none;
            }

            /* Show the indicator (dot/circle) when checked */
            .radio-container input:checked ~ .checkmark:after {
                display: block;
            }

            /* Style the indicator (dot/circle) */
            .radio-container .checkmark:after {
                top: 4px;
                left: 4px;
                width: 10px;
                height: 10px;
                border-radius: 50%;
                background: #edf100;
            }
			#t_div{
 padding-top: 12px;
padding-bottom: 12px;
background-color: #4E9AEC;
color: white;
text-align: center;
font-weight: bold;
font-size: x-large;
}
	.alert-dark {
    color: #a94442  !important;
    background-color: #d3d3d4 !important;
    border-color: #bcbebf  !important;
}
.alert-info {
    background-color: #d9edf7 !important;
    border-color: #bce8f1 !important;
    color: #a94442 !important;
}
.alert-warning {
    background-color: #fcf8e3  !important;
    border-color: #faebcc  !important;
    color: #8a6d3b  !important;
}

.alert-danger {
    background-color: #f2dede !important;
    border-color: #ebccd1 !important;
    color: #a94442 !important;
}
        </style>
    </head>
    <body class="no-skin">
        <!-- .navbar-container -->
        <?php include("includes/header.php"); ?>
        <!-- /.navbar-container -->

        <div class="main-container " id="main-container"> 
            <!-- .sidebar-shortcuts -->
            <?php include("includes/left_sidebar.php"); ?>
            <!-- /.sidebar-shortcuts -->
            <div class="main-content">
                <div class="main-content-inner">
                    <div class="breadcrumbs " id="breadcrumbs">
                        <ul class="breadcrumb">
                            <li> <i class="ace-icon fa fa-home home-icon"></i> <a href="#">Home</a> </li>
                            <li> <a href="#">Reports</a> </li>
                            <li class="active">Project</li>
                        </ul>
                        <!-- /.breadcrumb -->
                        <div class="nav-search" id="nav-search">
                            <form class="form-search">
                                <span class="input-icon">
                                    <input type="text" placeholder="Search ..." class="nav-search-input" id="nav-search-input" autocomplete="off" />
                                    <i class="ace-icon fa fa-search nav-search-icon"></i> </span>
                            </form>
                        </div>
                        <!-- /.nav-search --> 
                    </div>
                    <div class="page-content">
                        <div class="page-header">
                            <h1> Master Status Report</h1>
                        </div>
                        <!-- /.page-header -->
                        <div class="row">
                            <div class="col-xs-12"> 
                                <!-- PAGE CONTENT BEGINS -->
                                <div class="widget-box">
                                    <div class="widget-header widget-header-blue widget-header-flat">
                                        <h4 class="widget-title lighter"></h4>
                                    </div>
                                    <div class="widget-body">
                                        <div class="widget-main">
                                            <div id="fuelux-wizard-container">
                                                <div class="step-content pos-rel">
                                                    <div class="step-pane active" data-step="1">
                                                        <form class="form-horizontal"  id="validation-form" method="post" autocomplete="off" onsubmit="return checkvalidate();">
                                                            <div class="row">
                                                                <div  class="form-group">
                                                                    <label class="control-label col-xs-12 col-sm-12 no-padding-right" for="email"> </label>
                                                                    <div class="col-xs-12 col-sm-9">
                                                                        <div class="clearfix">
                                                                            <input type="hidden" id="radio_check" name="radio_check" value="<?php
                                                                            if ($_REQUEST['radio']!="") {
                                                                                echo h($_REQUEST['radio']);
                                                                            }
                                                                            ?>">

                                                                            <?php
                                                                            foreach (array('wip', 'sfd', 'dp', 'dr', 'cr', 'ce') as $rk) {
                                                                                ${$rk . '_chk'} = ($_REQUEST['radio'] == $rk) ? 'checked="checked"' : '';
                                                                            }
                                                                            ?>
                                                                            <label class="radio-container">WIP
                                                                                <input type="radio" class="radio" <?php echo  $wip_chk ?> value="wip" name="radio" id="wip">
                                                                                <span class="checkmark"></span>
                                                                            </label>
                                                                            <label class="radio-container">Schedules for the Day
                                                                                <input type="radio" class="radio" name="radio" <?php echo  $sfd_chk ?> id="schedules" value="sfd">
                                                                                <span class="checkmark"></span>
                                                                            </label>
                                                                            <label class="radio-container">Delivery Performance
                                                                                <input type="radio" class="radio" name="radio" <?php echo  $dp_chk ?> id="delivery" value="dp">
                                                                                <span class="checkmark"></span>
                                                                            </label>
                                                                           <!-- <label class="radio-container">Detailed Report
                                                                                <input type="radio" class="radio" name="radio" <?php echo  $dr_chk ?> id="detail-report" value="dr">
                                                                                <span class="checkmark"></span>
                                                                            </label>-->
                                                                            <label class="radio-container">Consolidated-report
                                                                                <input type="radio" class="radio" name="radio" <?php echo  $cr_chk ?> id="consolidated-report" value="cr">
                                                                                <span class="checkmark"></span>
                                                                            </label>
																			<label class="radio-container">CE/PE -report
                                                                                <input type="radio" class="radio" name="radio" <?php echo  $ce_chk ?> id="cepe" value="ce">
                                                                                <span class="checkmark"></span>
                                                                            </label>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            <div class="row">
                                                                <!-- start sepration -->
                                                                <div class="col-lg-5">
                                                                    <div class="form-group" id="customer_select">
                                                                        <label class="control-label col-xs-12 col-sm-4 no-padding-right" for="email">Choose Customer:</label>
                                                                        <div class="col-xs-12 col-sm-8">
                                                                            <div class="clearfix">
                                                                                <?php
                                                                                if($is_cust_user) { 
		$query = "SELECT cust_name,cust_id,id FROM `adm_customer_master` where id = '".esc($_SESSION['cust_id'])."' OR FIND_IN_SET(id,'".esc($_SESSION['ocust_id'])."') ORDER BY `cust_name` ASC ";
		}
		else{
		$query = "SELECT cust_name,cust_id,id FROM `adm_customer_master` ORDER BY `cust_name` ASC ";
		}
                                                                                $customers = dbq($query);
                                                                                ?>
                                                                                <select id="cust_id" name="cust_id" class="col-xs-6  form-control" data-placeholder="Choose">
                                                                                    <option value="">-Select Customer-</option>
                                                                                    <option <?php if ($_REQUEST['cust_id'] == 'all') { ?> selected <?php } ?> value="all">-All-</option>
                                                                                    <?php while ($row = $customers->fetch_assoc()) { ?>
                                                                                        <option <?php if ($_REQUEST['cust_id'] == $row['id']) { ?> selected <?php } ?> value="<?php echo $row['id']; ?>"><?php echo h($row['cust_name']); ?></option>
                                                                                    <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div class="form-group" id="stage_cons">
                                                                        <label class="control-label col-xs-12 col-sm-4 no-padding-right" for="email">Choose Stage:</label>
                                                                        <div class="col-xs-12 col-sm-8">
                                                                            <div class="clearfix">

                                                                                <select id="stage_id" name="stage_id" class="col-xs-12 col-sm-4 form-control" data-placeholder="Choose">
                                                                                    <option value="">-Select Stage-</option>
                                                                                    <option <?php if ($_REQUEST['stage_id'] == 'all') { ?> selected <?php } ?> value="all">-All-</option>

                                                                                    <option value="FP" <?php if ($_REQUEST['stage_id'] == 'FP') { ?> selected <?php } ?> >-First Proof-</option>
                                                                                    <option value="REV" <?php if ($_REQUEST['stage_id'] == 'REV') { ?> selected <?php } ?> >-All Revises-</option>
                                                                                    <option value="REV1" <?php if ($_REQUEST['stage_id'] == 'REV1') { ?> selected <?php } ?> >-REV1-</option>
                                                                                    <option value="REV2" <?php if ($_REQUEST['stage_id'] == 'REV2') { ?> selected <?php } ?> >-REV2-</option>
                                                                                    <option value="REV3" <?php if ($_REQUEST['stage_id'] == 'REV3') { ?> selected <?php } ?> >-REV3-</option>
                                                                                    <option value="REV4" <?php if ($_REQUEST['stage_id'] == 'REV4') { ?> selected <?php } ?> >-REV4-</option>
                                                                                    <option value="REV5" <?php if ($_REQUEST['stage_id'] == 'REV5') { ?> selected <?php } ?> >-REV5-</option>
                                                                                    <option value="FIN" <?php if ($_REQUEST['stage_id'] == 'FIN') { ?> selected <?php } ?> >-Final-</option>
                                                                                    <option value="ISSCOR" <?php if ($_REQUEST['stage_id'] == 'ISSCOR') { ?> selected <?php } ?> >-Issue-</option>
                                                                                    <option value="Sample" <?php if ($_REQUEST['stage_id'] == 'Sample') { ?>selected<?php } ?> >-Sample-</option>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
																	
																	 <div class="form-group" id="dept_wip">
                                                                        <label class="control-label col-xs-12 col-sm-4 no-padding-right" for="email">Choose Department:</label>
                                                                        <div class="col-xs-12 col-sm-8">
                                                                            <div class="clearfix">

                                                                                  <?php
		$query = "SELECT dept_name,dept_code FROM `adm_dept_master` WHERE parent_id=0 ORDER BY `dept_name` ASC ";
		$customers = dbq($query);		
		
		?><select id="dep_id" name="dep_id" class="col-xs-12 form-control" data-placeholder="Choose" >
                                  <option value="">-Select-</option>
                                  <?php while($row = $customers->fetch_assoc()){ ?>
                                  <option value="<?php echo $row['dept_code']; ?>" <?php if($_REQUEST['dep_id'] ==$row['dept_code']){?> selected <?php } ?>><?php echo $row['dept_name']; ?></option>
                                  <?php } ?>
                                                                                </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
																	
																	 <div class="form-group" id="platform_dp" style="display:none;">
                                                                        <label class="control-label col-xs-12 col-sm-4 no-padding-right" for="email">Choose Platform:</label>
                                                                        <div class="col-xs-12 col-sm-8">
                                                                            <div class="clearfix">
						<select name="dp_platform" id="dp_platform">
                                <option value="">-Select-</option>
                                <?php foreach((isset($platform_journal_array) && is_array($platform_journal_array)) ? $platform_journal_array : array() as $id=>$val){?>
                                <option <?php if ($_REQUEST['dp_platform'] ==$val) { ?>selected<?php } ?> value="<?php echo $val; ?>"><?php echo $val; ?></option>
                                <?php } ?>
                              </select>
                                                                            </div>
                                                                        </div>
                                                                    </div>
																	  <div class="form-group" id="date_sdf" style="display:none;">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">Date:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="sdf_dt" name="sdf_dt" class="col-xs-12 col-sm-12 date-picker" value="<?php
                                                                                    if ($_REQUEST['sdf_dt'] != '') {
                                                                                        echo h($_REQUEST['sdf_dt']);
                                                                                    } else {
                                                                                        echo date('d-m-Y');
                                                                                    }
                                                                                    ?>" />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>
																		<?PHP 
																		$yds=date('d-m-Y', strtotime('-1 days'));
																		$tyds=date('Y-m-d');
																		$tds=date('d-m-Y', strtotime('+1 days'));
																		?>
																		 <div class="form-group" id="cepe_fsdf" style="display:none;">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">From Date:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="cfrom_dt" name="cfrom_dt" class="col-xs-12 col-sm-12 date-picker" value="<?php
                                                                                    if ($_REQUEST['cfrom_dt'] != '') {
                                                                                        echo h($_REQUEST['cfrom_dt']);
                                                                                    } else {
                                                                                        echo $yds;
                                                                                    }
                                                                                    ?>" />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>
																		
																		 <div class="form-group" id="cepe_tsdf" style="display:none;">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">To Date:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="cto_dt" name="cto_dt" class="col-xs-12 col-sm-12 date-picker" value="<?php
                                                                                    if ($_REQUEST['cto_dt'] != '') {
                                                                                        echo h($_REQUEST['cto_dt']);
                                                                                    } else {
                                                                                        echo $tds;
                                                                                    }
                                                                                    ?>" />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>
								<div class="form-group" id="status_wip" style="display:none;">

								<label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">Choose Status</label>
																		   <div class="col-xs-5  col-sm-6">
					<label class="inline">
					<input type="checkbox" id="inpt_status" name="inpt_status[]" class="ace" value="Query" <?php if (in_array("Query", $inpt_status)) { echo 'checked'; } ?>  />
					<span class="lbl"> Query</span>
					</label>
					<label class="inline">
					<input type="checkbox" id="inpt_status_hold" name="inpt_status[]" class="ace" value="Hold" <?php if (in_array("Hold", $inpt_status)) { echo 'checked'; } ?> />
					<span class="lbl"> Hold</span>
					</label>
					 </div>
					 </div>
				  
                                                                </div>
                                                                <div class="col-lg-1"></div>
                                                                <div class="col-lg-4" id="wip_nodate">
                                                                    <div class="form-group">
                                                                        <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="email">Duration : </label>
                                                                        <div class="col-xs-12 col-sm-9" style="margin-top: 8px;">
                                                                            <div class="clearfix">
                                                                                <label class="radio-container">Day
                                                                                    <input type="radio"  name="date_wise" <?php if ($_REQUEST['date_wise'] == 'd') { ?> checked <?php } ?> id="daywise" value="d">
                                                                                    <span class="checkmark"></span>
                                                                                </label>
                                                                                <label class="radio-container">Month
                                                                                    <input type="radio" name="date_wise" <?php if ($_REQUEST['date_wise'] == 'm') { ?> checked <?php } ?> id="monthwise" value="m">
                                                                                    <span class="checkmark"></span>
                                                                                </label>
                                                                                <label class="radio-container">Year
                                                                                    <input type="radio" name="date_wise" <?php if ($_REQUEST['date_wise'] == 'y') { ?> checked <?php } ?> id="yearwise" value="y">
                                                                                    <span class="checkmark"></span>
                                                                                </label>                                                                            
                                                                            </div>																
                                                                        </div>
                                                                    </div>
                                                                    <div id="day_div" style="display:<?php
                                                                    if ($_REQUEST['date_wise'] == 'd') {
                                                                        echo 'block';
                                                                    } else {
                                                                        echo 'none';
                                                                    }
                                                                    ?>;">
                                                                        <div class="form-group">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">From Date:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="from_dt" name="from_dt" class="col-xs-12 col-sm-12 date-picker" value="<?php
                                                                                    if ($_REQUEST['from_dt'] != '') {
                                                                                        echo h($_REQUEST['from_dt']);
                                                                                    } else {
                                                                                        echo date('d-m-Y');
                                                                                    }
                                                                                    ?>" />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>
                                                                        <div class="space-2"></div>
                                                                        <div class="form-group">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">To Date:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="to_dt" name="to_dt" class="col-xs-12 col-sm-12 date-picker endDate" value="<?php
                                                                                    if ($_REQUEST['to_dt'] != '') {
                                                                                        echo h($_REQUEST['to_dt']);
                                                                                    } else {
                                                                                        echo date('d-m-Y');
                                                                                    }
                                                                                    ?>"  />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div id="month_div" style="display:<?php
                                                                    if ($_REQUEST['date_wise'] == 'm') {
                                                                        echo 'block';
                                                                    } else {
                                                                        echo 'none';
                                                                    }
                                                                    ?>;">
                                                                        <div class="form-group">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">Month:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="mfrom_dt" name="mfrom_dt" class="col-xs-12 col-sm-12 date-picker mydatepicker" value="<?php
                                                                                    if ($_REQUEST['mfrom_dt'] != '') {
                                                                                        echo h($_REQUEST['mfrom_dt']);
                                                                                    } else {
                                                                                        echo date('m-Y');
                                                                                    }
                                                                                    ?>" />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                    <div id="year_div" style="display:<?php
                                                                    if ($_REQUEST['date_wise'] == 'y') {
                                                                        echo 'block';
                                                                    } else {
                                                                        echo 'none';
                                                                    }
                                                                    ?>;">
                                                                        <div class="form-group">
                                                                            <label class="control-label col-xs-12 col-sm-3 no-padding-right" for="name">Year:</label>
                                                                            <div class="col-xs-5  col-sm-6">
                                                                                <div class="input-group">
                                                                                    <input type="text" id="yfrom_dt" name="yfrom_dt" class="col-xs-12 col-sm-12 date-picker fydatepicker" value="<?php
                                                                                    if ($_REQUEST['yfrom_dt'] != '') {
                                                                                        echo h($_REQUEST['yfrom_dt']);
                                                                                    } else {
                                                                                        echo date('Y');
                                                                                    }
                                                                                    ?>" />
                                                                                    <span class="input-group-addon"> <i class="ace-icon fa fa-calendar bigger-110"></i> </span> </div>
                                                                            </div>
                                                                        </div>

                                                                    </div>

                                                                </div>

                                                                <div class="space-2"></div>
                                                                <div class="col-lg-12">
                                                                    <div class="form-group">
                                                                        <div class="col-xs-12 col-sm-12 center">
                                                                            <input type="hidden" name="action" value="export">
                                                                            <a href="<?php echo h($self); ?>" class="btn btn-danger">Reset Data</a>

                                                                            <button type="submit" value="Save" name="btn_rinvoice" class="btn btn-success">Show Data</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </form>
                                                    </div>
                                                    <br>

                                                    <?php
													//echo h($_REQUEST['radio']);
													if ($_REQUEST['radio']=='wip') {
														
														$wip_from = "FROM `inw_book_dtl` as wd LEFT JOIN inw_book_revisions_dtl as r ON (wd.`id` = r.`b_id` AND r.r_id = (SELECT MAX(r2.r_id) FROM inw_book_revisions_dtl as r2 WHERE r2.b_id = wd.`id`)), adm_customer_master as c WHERE wd.cust_id = c.id ";
													if ($is_cust_user) {
														$wip_scope = " AND (wd.cust_id = '" . esc($_SESSION['cust_id']) . "' OR FIND_IN_SET(wd.cust_id,'" . esc($_SESSION['ocust_id']) . "')) AND wd.status NOT IN ('Client Review','Completed') ";
													} else {
														$wip_scope = " AND wd.status NOT IN ('Client Review','Completed') ";
													}
													$wip_where = $wip_scope . " $cccb $search1 $search2 $search3 " . $_SESSION['inpt_status_qry'] . " ";
													$query = "SELECT c.cust_name, wd.*, r.received_date, r.due_date, r.correction_pages, r.revision_count " . $wip_from . $wip_where;
													$cnt_query = "SELECT COUNT(wd.id) as num " . $wip_from . $wip_where;
$customers = dbq($cnt_query);
													$page_count = $customers->fetch_assoc();
													$total_pages = isset($page_count['num']) ? (int) $page_count['num'] : 0;
													
													
													$order_by = "ORDER BY FIELD(highpriority, 1) desc,`due_dt` asc";
													$start = max(0, (int) $_REQUEST['start']);
										$filePath = $self . '?page=1&' . msr_qs($filePathKeys);
													$limit =10; //how many items to show per page
													
													$query = $query. $order_by." LIMIT $start, $limit";  
													//echo $query;
													$articles = dbq($query);	
                                                    $articles->num_rows;
													//echo "fdgfdg".$total_pages;
                                                   
													
												

												
                                                    ?>   
															<div class="page-header">
                                                            <h1>WIP Report <b style="color:red;font-size:14px;"> - Total count :<?php echo $total_pages;?></b> <a href="download-excel-wip-report.php?<?php echo h(msr_qs(array('cust_id', 'j_id', 'stage_id', 'dp_platform', 'dep_id', 'radio'))); ?>" class="btn btn-primary">Download Excel </a></h1> 				

                                                        </div>
                                                    <div class="myTable1">

                                                        <table class="table table-striped table-bordered table-hover">
                                                            <thead>
                                                            <th>Client </th>
                                                            <th>Book Name</th>
															<?php 
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
                                                            <th>Stage</th>
															<?php 
															}
															?>
                                                            <th>Received Date</th>
															<?php 
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
                                                             <th>Due Date</th>
															<?php 
															}
															 
															if($_REQUEST['dep_id']=='1' || $_REQUEST['dep_id']=='2')
															{
															?>
                                                              <th>Alloted to</th>
                                                              <th>Alloted Due date</th>
															<?php 
															}
															?>
                                                            <th>Status</th>
                                                            <th>Graphics</th>
                                                            </thead>
                                                            <tbody id="tbl1Body">
                                                                <?php
                                                                if ($articles->num_rows > 0) {
                                                                    ?>
                                                                    <?php
                                                                    while ($row_history = $articles->fetch_assoc()) {
																		
																		$stage_r=preg_replace("/REV([0-9]+)/","REV",$row_history['stage']);
																		$stage_i=preg_replace("/ISSCOR([0-9]+)/","ISSCOR",$row_history['stage']);
                                                                        //print_r($row_history);
																		
																		$trans_sql = "SELECT u.full_name,t.current_status,t.completion_status,t.comments,t.id FROM `inw_transactions` as t,users as u WHERE t.process_user = u.id AND t.`project_id` = '".$row_history['id']."' AND t.created_dt > '2020-03-01'  AND t.current_status NOT IN ('Completed','Take Over') AND t.stage = '".$row_history['stage']."' AND (t.id=(select id from `inw_transactions` where dept!=9 and `project_id` = '".$row_history['id']."' ORDER BY `id` DESC limit 1))  ORDER BY t.`id` DESC limit 1;";
		//echo $trans_sql.'<br>';		
																			$chk_transaction = dbq($trans_sql); 
																			$transaction_toatl_count=$chk_transaction->num_rows;
																			$trans_res = $chk_transaction->fetch_assoc();	
																			$assign_comments=dbq("select * from inw_transactions where project_id='".$row_history['id']."' and dept='14' and completion_status=6 order by id desc limit 1");
																			$num_acount=$assign_comments->num_rows;
																			$res_ac=$assign_comments->fetch_array();
																			
															if($_REQUEST['dep_id']=='1' || $_REQUEST['dep_id']=='2')
															{
															$assign_cepe=dbq_row("select * from users where id='".$row_history['assigned_user_id']."'");
															}				

		if($row_history['stage'] == "FP")
		{
			$due_dt = (date('d-m-Y',strtotime($row_history['due_dt'])));
			$recv_dt = (date('d-m-Y',strtotime($row_history['recv_dt'])));
			$manuscript_count = $row_history['manuscript_count'];
		}
		else
		{
			$due_dt = (date('d-m-Y',strtotime($row_history['due_date'])));
			$recv_dt = (date('d-m-Y',strtotime($row_history['received_date'])));
			$manuscript_count = $row_history['correction_pages'];
		}
		
		$tr_class = '';
		
		$current_dateTimestamp1 = strtotime(date('d-m-Y')); 
		$Due_dateTimestamp2 = strtotime($due_dt); 
		if($row_history['highpriority'] == 1)
		{
			$tr_class = 'alert alert-info';
		}
		elseif(($row_history['status'] == 'Query') || ($row_history['status'] == 'Hold'))
		{
			$tr_class = 'alert alert-dark';
		}
		elseif($current_dateTimestamp1 > $Due_dateTimestamp2)
		{
			$tr_class = 'alert alert-danger';
		}
		
                                                                        ?>
                                                                        <tr class="<?php echo $tr_class;?>">
                                                                            <td><?php echo h($row_history['cust_name']) ?></td>
                                                                           
                                                                            <td><?php echo h($row_history['book_short_name']) ?></td>
                                                                            
                                                                                <?php
                                                                            if ($row_history['stage'] == 'FP') {
                                                                              
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
																				<td><?php echo h($row_history['stage']); ?></td>
															<?php 
															}
															?>
                                                                                <td><?php echo fmt_dt($row_history['recv_dt']) ?></td>
																				
																			<?php 
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
                                                                                <td><?php echo fmt_dt($row_history['due_dt']) ?></td>
															<?php 
															}
														
                                                                            } elseif($stage_r=='REV') {
                                                                               
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
																				 <td><?php echo  "REV" . $row_history['revision_count'] ?></td>
															<?php 
															}
															?>
                                                                                <td><?php echo fmt_dt($row_history['received_date']) ?></td>

																				
																				<?php 
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
                                                                                <td><?php echo fmt_dt($row_history['due_date']) ?></td>
															<?php 
															}
															
                                                                            }
																			elseif($stage_i=='ISSCOR') {
                                                                               
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
																				 <td><?php echo h($row_history['stage']) ?></td>
															<?php 
															}
															?>
                                                                                <td><?php echo fmt_dt($row_history['created_dt']) ?></td>

																				
																				<?php 
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
                                                                                <td><?php echo fmt_dt($row_history['due_dt']) ?></td>
															<?php 
															}
															
                                                                            }
																			else {
                                                                                
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
																				 <td><?php echo h($row_history['stage']) ?></td>
															<?php 
															}
															?>
                                                                                <td><?php echo fmt_dt($row_history['received_date']) ?></td>

																				<?php 
															if(($_REQUEST['dep_id']!=1) && ($_REQUEST['dep_id']!=2))
															{
															?>
                                                                                <td><?php echo fmt_dt($row_history['due_date']) ?></td>
															<?php 
															}
															
                                                                            }
                                                                           
															if($_REQUEST['dep_id']=='1' || $_REQUEST['dep_id']=='2')
															{
															?>
															<td><?php echo h($assign_cepe['full_name']);?></td>
															<td><?php echo fmt_dt($row_history['ce_pe_due_date']) ?></td>
															<?php 
															}
															?>
                                                                             <td>
                                                                                <span class="label label-sm label-success"><?php echo h($row_history['status']); ?></span>
        <?php
        if ($transaction_toatl_count > 0) {
            echo '<span class="label label-sm label-info">' . h($trans_res['full_name']) . '</span>|<span class="label label-sm label-warning">' . h($trans_res['current_status']) . '</span>';
        }
		if($num_acount>0 && $res_ac['comments']!='') 
		{
            echo '|<span class="label label-sm label-danger">' . h($res_ac['comments']) . '</span>';
        }
		elseif($num_acount>0 && $res_ac['comments']=='') 
		{
			echo '|<span class="label label-sm label-danger">No Comments</span>';
		}
        ?>
                                                                            </td>
                                                                                <td><?php if ($row_history['fig_complete_status'] == '1') { echo "Yes";}else{ echo 'No';}?></td>
                                                                                

                                                                        </tr>
                                                                        <?php
                                                                        //}
                                                                        $i++;
                                                                    }
                                                                }
                                                                ?>
                                                            <?php  if($total_pages > $limit) { ?>
           	<tr>
            <td colspan="4">
            <div class="col-xs-12"><div class="dataTables_info" id="dynamic-table_info" role="status" aria-live="polite">Showing <?php echo ($start+1); ?> to <?php echo ($start+$limit > $total_pages) ? $total_pages : $start+$limit; ?> of <?php echo $total_pages; ?> entries</div></div>
            </td>
					<td align="center" colspan="8" class="inactive"><div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
            <ul class="pagination">
              <?php paginate($start,$limit,$total_pages,$filePath,$otherParams); ?>
            </ul>
            </div></td>
				  </tr>
            <?php }?>
</tbody>
</table>
                                                    </div>   
													
										<div >
                                                        <div class="page-header">
                                                            <h1>Consolidated-Report</h1>
                                                        </div>

                                                        <table class="table table-striped table-bordered table-hover " style="width: 70%; margin-top: 30px; margin-left: 160px;">
                                                            <thead>
                                                                <tr>
                                                                    <th rowspan="2">Customer</th>
                                                                    <th colspan="3">Stages</th>
                                                                </tr>
                                                                <tr>
                                                                    <th>FP</th>
                                                                    <th>REV</th>
                                                                    <th>FIN</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
<?php
    if($_REQUEST['cust_id']!="" && $_REQUEST['cust_id']!="all")
	{
    $sql_query = "SELECT * FROM `adm_customer_master` where id='".esc($_REQUEST['cust_id'])."' ";
	}
	else
	{
    $sql_query = "SELECT * FROM `adm_customer_master`";
	}
    $run_row = dbq($sql_query);
    while ($row = $run_row->fetch_assoc()) {

		?>
		  <tr>
           <td rowspan="1"><?php echo h($row['cust_name']); ?> </td>
		<?php 
   
            
            
            $fp_query = "SELECT count(id)as stage_count FROM `inw_book_dtl` WHERE cust_id =".$row['id']."  AND  stage = 'FP' AND status NOT IN ('Delivery','Client Review','Completed')   $searchfp $search_cy  $search_cm $search_cd ";
            $fp_run_query = dbq($fp_query);
            $fp_row = $fp_run_query->fetch_assoc();
            $fp_id_count = $fp_row['stage_count'];

            $rev_query = "SELECT count(id)as rev_count FROM `inw_book_dtl` WHERE cust_id =".$row['id']." AND status NOT IN ('Delivery','Client Review','Completed') AND stage LIKE 'REV%' $searchfp  $search_cy  $search_cm $search_cd";
            //$rev_query = "SELECT stage as rev_count FROM `inw_inward_dtl` WHERE cust_id = $cust_ids AND j_id = $j_id Order by stage DESC limit 1 ";
            $fev_run_query = dbq($rev_query);
            $fev_row = $fev_run_query->fetch_assoc();
            $fev_id_count = $fev_row['rev_count'];


            $fin_query = "SELECT COUNT(id)as id FROM `inw_book_dtl` WHERE cust_id =".$row['id']." AND status NOT IN ('Delivery','Client Review','Completed') AND  stage LIKE '%FIN%' $searchfp $search_cy  $search_cm $search_cd";
            $fin_run_query = dbq($fin_query);
            $fin_row = $fin_run_query->fetch_assoc();
            $fin_id_count = $fin_row['id'];
		 

                ?>
                                                                              
                                                                                    
                                                                                    <td><?php echo  $fp_id_count; ?></td>
                                                                                    <td><?php echo  $fev_id_count; ?></td>
                                                                                    <td><?php echo  $fin_id_count; ?></td>
                                                                                </tr>
                <?php
              
            
        
    }
//}
?>
                                                            </tbody>
                                                        </table>
                                                    </div>			
			<?php  } ?>
													
													 <?php
													//echo h($_REQUEST['radio']);
													if ($_REQUEST['radio']=='sfd') {
															$today_date = date("Y-m-d");  /// today date
														
                                                      $query = "SELECT wd.id,wd.cust_id, wd.status, wd.due_dt,  wd.recv_dt, wd.book_short_name, wd.stage, wd.department_id FROM inw_book_dtl as wd WHERE   wd.status != 'Client Review'  AND wd.created_dt > '2020-03-01' AND wd.stage='FP' $cccb  $search $search2 ORDER BY wd.id desc";
													  
													 
	$articledetails = dbq($query);
	$filecount = 1;
	if($articledetails->num_rows > 0 )
	{
													
													

												
                                                    ?>   
														<div class="page-header">
                                                            <h1>First Proof Articles<b style="color:red;font-size:18px;"> - Total count :<?php echo $articledetails->num_rows;?></b></h1>
                                                        </div>
                                                    <div class="myTable1">
														<div id="t_div">First Proof Articles</div>
														
                                                        <table class="table table-striped table-bordered table-hover">
                                                            <thead>
                                                            <th>Client </th>
                                                            <th>Book Name</th>
                                                            <th>Stage</th>
                                                            <th>Received Date</th>
                                                            <th>Due Date</th>
															<th>Status</th>
                                                            </thead>
                                                            <tbody id="tbl1Body">
                                                                <?php
                                                                while ($article_info = $articledetails->fetch_assoc()) 
		   {
			   	/// Get the customers details ////
				$query = "SELECT cust_name,cust_id,id FROM `adm_customer_master` WHERE id = '".$article_info['cust_id']."' ";
				$customers = dbq($query);
				$crow = $customers->fetch_assoc();
				
				
				
				
				
                // $query_disp = dbq_row("SELECT * FROM `inw_dispatch_history` WHERE a_id ='" . $article_info['id'] . "' ORDER BY `idh_id` DESC limit 1 ");
				 
				$send_dts = date('d-m-Y');
                 $due_dts=date('d-m-Y', strtotime($article_info['due_dt']));
				 
				 $trans_sql = "SELECT u.full_name,t.current_status,t.id FROM `inw_transactions` as t,users as u WHERE t.process_user = u.id AND t.`project_id` = '".$article_info['id']."' AND t.created_dt > '2020-03-01'  AND t.current_status NOT IN ('Completed','Take Over') AND t.stage = '".$article_info['stage']."' AND ('Completed' != (select current_status from `inw_transactions` where `project_id` = '".$article_info['id']."' ORDER BY `id` DESC limit 1) )  ORDER BY t.`id` DESC limit 1;";
		//echo $trans_sql.'<br>';		
																			$chk_transaction = dbq($trans_sql); 
																			$transaction_toatl_count=$chk_transaction->num_rows;
																			$trans_res = $chk_transaction->fetch_assoc();
                                                  ?>
                                                                        <tr  <?php if (strtotime($send_dts) > strtotime($due_dts)) { echo "style='color:red;'";}?>>
                                                                            <td><?php echo h($crow['cust_name']) ?></td>
                                                                            
                                                                            <td><?php echo h($article_info['book_short_name']) ?></td>
                                                                           <td><?php echo h($article_info['stage']); ?></td>
                                                                           <td><?php echo fmt_dt($article_info['recv_dt']) ?></td>
                                                                            <td><?php echo fmt_dt($article_info['due_dt']) ?></td>
																		
                                                                            <td>    <span class="label label-sm label-success"><?php echo h($article_info['status']); ?></span>
        <?php
        if ($transaction_toatl_count > 0) {
            echo '<span class="label label-sm label-info">' . h($trans_res['full_name']) . '</span>|<span class="label label-sm label-warning">' . h($trans_res['current_status']) . '</span>';
        }
        ?>
                                                                            </td>

                                                                        </tr>
                                                                        <?php
                                                                        
                                                                    }
                                                                ?>
                                                            </tbody>
                                                        </table>
														
														
                                                    </div>  
													
													     <?php
                                                                        
                                                                }
                                                                ?>
													<?php 
													 $querys = "SELECT wd.id,wd.cust_id, wd.book_short_name, wd.status, r.due_date, r.received_date, wd.pub_id, wd.stage, wd.department_id FROM inw_book_dtl as wd LEFT JOIN inw_book_revisions_dtl as r ON (wd.`id` =r.`b_id` and r.r_id = (select r_id from inw_book_revisions_dtl where b_id = wd.`id` order by r_id DESC limit 1))   WHERE   wd.status != 'Client Review'  AND  DATE_FORMAT(r.due_date,'%Y-%m-%d')<='" .$today_date."' AND r.created_on > '2020-03-01' AND wd.stage LIKE 'REV%' AND wd.id = r.b_id $cccb  $search1 $search2 $search3 GROUP BY r.b_id ORDER BY wd.cust_id ASC";
													//echo $querys;
	$articledetails = dbq($querys);
	//echo  $articledetails->num_rows;
	if($articledetails->num_rows > 0 )
	{
	?>													<div class="page-header">
                                                            <h1>Revises Articles<b style="color:red;font-size:18px;"> - Total count :<?php echo $articledetails->num_rows;?></b></h1>
                                                        </div>
													 <div class="myTable1">
														<div id="t_div">Revises Articles</div>
                                                        <table class="table table-striped table-bordered table-hover">
                                                            <thead>
                                                            <th>Client </th>
															<th>Book Name</th>
                                                            <th>Stage</th>
                                                            <th>Received Date</th>
                                                            <th>Due Date</th>
															<th>Status</th>
                                                            </thead>
                                                            <tbody id="tbl1Body">
                                                                <?php
                                                                while ($article_info = $articledetails->fetch_assoc()) 
		   {
			   	/// Get the customers details ////
				$query = "SELECT cust_name,cust_id,id FROM `adm_customer_master` WHERE id = '".$article_info['cust_id']."' ";
				$customers = dbq($query);
				$crow = $customers->fetch_assoc();
				
				
				
				
                  $send_dts = date('d-m-Y');
                 $due_dts=date('d-m-Y', strtotime($article_info['due_date']));
				 
				  $trans_sql = "SELECT u.full_name,t.current_status,t.id FROM `inw_transactions` as t,users as u WHERE t.process_user = u.id AND t.`project_id` = '".$article_info['id']."' AND t.created_dt > '2020-03-01'  AND t.current_status NOT IN ('Completed','Take Over') AND t.stage = '".$article_info['stage']."' AND ('Completed' != (select current_status from `inw_transactions` where `project_id` = '".$article_info['id']."' ORDER BY `id` DESC limit 1) )  ORDER BY t.`id` DESC limit 1;";
		//echo $trans_sql.'<br>';		
																			$chk_transaction = dbq($trans_sql); 
																			$transaction_toatl_count=$chk_transaction->num_rows;
																			$trans_res = $chk_transaction->fetch_assoc();
                                                  ?>
                                                                        <tr  <?php if (strtotime($send_dts) > strtotime($due_dts)) { echo "style='color:red;'";}?>>
                                                                            <td><?php echo h($crow['cust_name']) ?></td>
                                                                            
                                                                            <td><?php echo h($article_info['book_short_name']) ?></td>
                                                                           <td><?php echo h($article_info['stage']); ?></td>
                                                                           <td><?php echo fmt_dt($article_info['received_date']) ?></td>
                                                                            <td><?php echo fmt_dt($article_info['due_date']) ?></td>
                                                                             <td>
                                                                                <span class="label label-sm label-success"><?php echo h($article_info['status']); ?></span>
        <?php
        if ($transaction_toatl_count > 0) {
            echo '<span class="label label-sm label-info">' . h($trans_res['full_name']) . '</span>|<span class="label label-sm label-warning">' . h($trans_res['current_status']) . '</span>';
        }
        ?>
                                                                            </td>

                                                                        </tr>
                                                                          <?php
                                                                        
                                                                    }
                                                                ?>
                                                            </tbody>
                                                        </table>
														
														
                                                    </div>  
													
													     <?php
                                                                        
                                                                }
                                                                ?>
														
														
														
															<?php 
													 $querys = "SELECT wd.id,wd.cust_id, wd.book_short_name, wd.status, r.due_date, r.received_date, wd.pub_id, wd.stage, wd.department_id FROM inw_book_dtl as wd LEFT JOIN inw_book_revisions_dtl as r ON (wd.`id` =r.`b_id` and r.r_id = (select r_id from inw_book_revisions_dtl where b_id = wd.`id` order by r_id DESC limit 1))   WHERE    wd.status != 'Client Review' AND DATE_FORMAT(r.due_date,'%Y-%m-%d')<='" .$today_date."' AND r.created_on > '2020-03-01' AND wd.stage LIKE 'FIN%' AND wd.id = r.b_id $cccb  $search1 $search2 $search3 GROUP BY r.b_id ORDER BY wd.cust_id ASC";
													
	$articledetails = dbq($querys);
	//echo  $articledetails->num_rows;
	if($articledetails->num_rows > 0 )
	{
	?>													<div class="page-header">
                                                            <h1>Finals Articles<b style="color:red;font-size:18px;"> - Total count :<?php echo $articledetails->num_rows;?></b></h1>
                                                        </div>
													 <div class="myTable1">
														<div id="t_div">Finals Articles</div>
                                                        <table class="table table-striped table-bordered table-hover">
                                                            <thead>
                                                            <th>Client </th>
															<th>Book Name</th>
                                                            <th>Stage</th>
                                                            <th>Received Date</th>
                                                            <th>Due Date</th>
															<th>Status</th>
                                                            </thead>
                                                            <tbody id="tbl1Body">
                                                                <?php
                                                                while ($article_info = $articledetails->fetch_assoc()) 
		   {
			   	/// Get the customers details ////
				$query = "SELECT cust_name,cust_id,id FROM `adm_customer_master` WHERE id = '".$article_info['cust_id']."' ";
				$customers = dbq($query);
				$crow = $customers->fetch_assoc();
				
				
				
                  $send_dts = date('d-m-Y');
                 $due_dts=date('d-m-Y', strtotime($article_info['due_date']));
				 
				  $trans_sql = "SELECT u.full_name,t.current_status,t.id FROM `inw_transactions` as t,users as u WHERE t.process_user = u.id AND t.`project_id` = '".$article_info['id']."' AND t.created_dt > '2020-03-01'  AND t.current_status NOT IN ('Completed','Take Over') AND t.stage = '".$article_info['stage']."' AND ('Completed' != (select current_status from `inw_transactions` where `project_id` = '".$article_info['id']."' ORDER BY `id` DESC limit 1) )  ORDER BY t.`id` DESC limit 1;";
		//echo $trans_sql.'<br>';		
																			$chk_transaction = dbq($trans_sql); 
																			$transaction_toatl_count=$chk_transaction->num_rows;
																			$trans_res = $chk_transaction->fetch_assoc();
                                                  ?>
                                                                        <tr  <?php if (strtotime($send_dts) > strtotime($due_dts)) { echo "style='color:red;'";}?>>
                                                                            <td><?php echo h($crow['cust_name']) ?></td>
                                                                            
                                                                            <td><?php echo h($article_info['book_short_name']) ?></td>
                                                                           <td><?php echo h($article_info['stage']); ?></td>
                                                                           <td><?php echo fmt_dt($article_info['received_date']) ?></td>
                                                                            <td><?php echo fmt_dt($article_info['due_date']) ?></td>
                                                                              <td>
                                                                                <span class="label label-sm label-success"><?php echo h($article_info['status']); ?></span>
        <?php
        if ($transaction_toatl_count > 0) {
            echo '<span class="label label-sm label-info">' . h($trans_res['full_name']) . '</span>|<span class="label label-sm label-warning">' . h($trans_res['current_status']) . '</span>';
        }
        ?>
                                                                            </td>

                                                                        </tr>
                                                                          <?php
                                                                        
                                                                    }
                                                                ?>
                                                            </tbody>
                                                        </table>
														
														
                                                    </div>  
													
													     <?php
                                                                        
                                                                }
													}
													
													if ($_REQUEST['radio']=='dp') {
														?>
														
													
														<table class="table table-striped table-bordered table-hover">
      <tbody>
        <tr>
          <td class=""><?php
	$zero_cust=array();
    $sql_query = "SELECT * FROM `adm_customer_master`";	
    $run_row = dbq($sql_query);	
    while ($row = $run_row->fetch_assoc()) 
	{		
        
		 
			
			
			$cnt_query = "SELECT COUNT(wd.idh_id) as sent_count FROM inw_dispatch_history as wd, `inw_inward_dtl` as wdd, adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id and wd.a_id = wdd.id  AND wdd.cust_id = ".$row['id']."  $ccc  $search $search2 $search4";			
			
            $fp_run_query = dbq($cnt_query);
            $fp_row = $fp_run_query->fetch_assoc();
            $sent_count = $fp_row['sent_count'];
			if($sent_count>0)
			{
			?>
            <p class="col-md-2" style="color:<?php echo $row['font_color'];?> !important;"> <?php echo h($row['cust_name']); ?> - <?php echo  $sent_count; ?></p>
            <?php  
			}
			else
			{ 
			$zero_cust[]=$row['id'];
			}			
    }
?>
<?php  $implode = implode(',', $zero_cust);?>
</td>
        </tr>
      </tbody>
    </table>
	<table class="table table-striped table-bordered table-hover">
      <tbody>
        <tr>
          <td class=""><?php
	
    $sql_query = "SELECT * FROM `adm_customer_master` where FIND_IN_SET(id,'".$implode."')";	
    $run_row = dbq($sql_query);	
    while ($row = $run_row->fetch_assoc()) 
	{		
        
		 
			
			
			$cnt_query = "SELECT COUNT(wd.idh_id) as sent_count FROM inw_dispatch_history as wd, `inw_inward_dtl` as wdd, adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id and wd.a_id = wdd.id  AND wdd.cust_id = ".$row['id']."  $ccc  $search $search2 $search4";			
			
            $fp_run_query = dbq($cnt_query);
            $fp_row = $fp_run_query->fetch_assoc();
            $sent_count = $fp_row['sent_count'];
			if($sent_count==0)
			{
			?>
            <p class="col-md-2" style="color:<?php echo $row['font_color'];?> !important;"> <?php echo h($row['cust_name']); ?> - <?php echo  $sent_count; ?></p>
            <?php  
			}
			?>
            <?php        
    }
?></td>
        </tr>
      </tbody>
    </table>
														<?php 
														  $_REQUEST['radio'];
                                                            $query = "SELECT *,c.cust_name,j.j_code from inw_dispatch_history as wd, adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  $ccc  $search $search2 $search4 ";
														
														$cnt_query = "SELECT COUNT(wd.a_id) as num from inw_dispatch_history as wd,adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  $ccc  $search $search2  $search4 ";
														
															 $customers = dbq($cnt_query);
													 $page_count = $customers->fetch_assoc();
												 	 $total_pages = isset($page_count['num']) ? (int) $page_count['num'] : 0;
													$order_by = "ORDER BY `idh_id` DESC";
													$start = max(0, (int) $_REQUEST['start']);
		
										$filePath = $self . '?page=1&' . msr_qs($filePathKeys);
													 $limit =10; //how many items to show per page
													
													 $query = $query. $order_by." LIMIT $start, $limit";  
													//echo $query;
													$articles = dbq($query);	
                                                     $articles->num_rows;
													//echo "fdgfdg".$total_pages;
                                                   
													
													

												
                                                    ?>   
														<?php if ($total_pages == 0) { ?>
<div class="alert alert-warning" style="margin-top:15px;">No delivery records found. <b>Delivery Performance still reads the journal dispatch tables</b> (<code>inw_dispatch_history</code> / <code>adm_journals</code>), not the book tables, so it stays empty until it is pointed at the book dispatch table. Run <code>debug_master_status_report.php</code> (section 5) to check.</div>
<?php } ?>
<div class="page-header">
                                                            <h1>Delivery Performance Report <b style="color:red;font-size:14px;"> - Total count :<?php echo $total_pages;?></b> <a href="download-excel-dp-report.php?<?php echo h(msr_qs(array('cust_id', 'j_id', 'stage_id', 'dp_platform', 'radio', 'from_dt', 'to_dt', 'mfrom_dt', 'yfrom_dt', 'date_wise'))); ?>" class="btn btn-primary">Download Excel </a></h1>
                                                        </div>
                                                    <div class="myTable1">

                                                        <table class="table table-striped table-bordered table-hover">
                                                            <thead>
                                                            <th>Client </th>
                                                            <th>Journal Name</th>
                                                            <th>FileName</th>
                                                            <th>Stage</th>
                                                            <th>Received Date</th>
                                                            <th>Due Date</th>
															<th>Dispatched Date</th>
															<th>Page count</th>
                                                            <th>Schedule</th>                                                           
                                                            <th>Job Card</th>   
															</thead>
                                                            <tbody id="tbl1Body">
                                                                <?php
                                                                if ($articles->num_rows > 0) {
                                                                    ?>
                                                                    <?php
                                                                    while ($row_history = $articles->fetch_assoc()) {
																		
																		$stage_r=preg_replace("/REV([0-9]+)/","REV",$row_history['stage']);
																		$stage_i=preg_replace("/ISSCOR([0-9]+)/","ISSCOR",$row_history['stage']);
                                                                        //print_r($row_history);
																		
																	
		
		$query_disp = dbq_row("SELECT * FROM `inw_inward_dtl` WHERE id ='" . $row_history['a_id'] . "'");
		
		
		if($stage_r != 'REV')
		{
			$due_dt = (date('d-m-Y',strtotime($query_disp['due_dt'])));
			$recv_dt = (date('d-m-Y',strtotime($query_disp['recv_dt'])));
			$manuscript_count = $query_disp['manuscript_count'];
			$pg_count = $query_disp['page_count'];
		}
		else
		{
			 $rev_rep=str_replace("REV","",$row_history['stage']);
			$query = "SELECT * FROM inw_revisions_dtl WHERE inw_id = '".$row_history['a_id']."' and revision_count='".$rev_rep."'";
		    $revin = dbq($query);
		    $revinfo = $revin->fetch_assoc();
			$due_dt = (date('d-m-Y',strtotime($revinfo['due_date'])));
			$recv_dt = (date('d-m-Y',strtotime($revinfo['received_date'])));
			//$manuscript_count = $revinfo['correction_pages'];
			$pg_count = $revinfo['correction_pages'];
		}
																			//$chk_transaction = dbq($trans_sql); 
																			//$transaction_toatl_count=$chk_transaction->num_rows;
																			//$trans_res = $chk_transaction->fetch_assoc();	
                                                                        $send_dt = date('d-m-Y', strtotime($row_history['sent_date']));
                                                                        $send_dts = date('d-m-Y', strtotime($row_history['sent_date']));
                                                                        ?>
                                                                        <tr>
                                                                            <td><?php echo h($row_history['cust_name']) ?></td>
                                                                            <td><?php echo h($row_history['j_code']) ?></td>
                                                                            <td><?php echo h($query_disp['pub_id']) ?></td>
                                                                            
                                                                                <?php
                                                                            if ($row_history['stage'] == 'FP') {
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																				<td><?php echo h($row_history['stage']); ?></td>
                                                                                <td><?php echo fmt_dt($recv_dt) ?></td>
                                                                                <td><?php echo fmt_dt($due_dt) ?></td>
                                                                                <?php
                                                                            } elseif($stage_r=='REV') {
																				
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																				<td><?php echo h($row_history['stage']); ?></td>
                                                                                 <td><?php echo fmt_dt($recv_dt) ?></td>
                                                                                <td><?php echo fmt_dt($due_dt) ?></td>
                                                                                <?php
                                                                            }
																			elseif($stage_i=='ISSCOR') {
																				
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																				 <td><?php echo h($row_history['stage']) ?></td>
                                                                                <td><?php echo fmt_dt($query_disp['created_dt']); ?></td>

                                                                                <td><?php echo fmt_dt($query_disp['due_dt']); ?></td>
                                                                                <?php
                                                                            }
																			else{
																				
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																				 <td><?php echo h($row_history['stage']) ?></td>
                                                                                <td><?php echo fmt_dt($query_disp['created_dt']) ?></td>

                                                                                <td><?php echo fmt_dt($query_disp['due_dt']) ?></td>
                                                                                <?php
                                                                            }
																			
                                                                            ?>

                                                                             <td><?php echo $send_dt; ?></td>
																																																							<td><?php echo $pg_count; ?></td>

                                                                            <td>	<?php
                                                                  if (strtotime($send_dts) == strtotime($due_dts)) {
                                                                    $l++;

                                                                    echo "On Time";
																	
                                                                } elseif (strtotime($send_dts) > strtotime($due_dts)) {
                                                                    $j++;
                                                                    echo "Delay";
                                                                } elseif (strtotime($send_dts) < strtotime($due_dts)) {
                                                                    $k++;
                                                                    echo "Ahead";
                                                                }
															     	
                                                                        ?>
                                                                            </td>
                    <td><a title="Job Card" href="job_card.php?id=<?php echo $query_disp['id']; ?>" target="_blank" class="btn btn-xs btn-info"><i class="ace-icon fa fa-clock-o bigger-120"></i></a> </td> 

                                                                        </tr>
                                                                        <?php
                                                                        $i++;
                                                                    }
                                                                }
																	
																	
																
															
                                                                ?>
                                                         
														
														 <?php  if($total_pages > $limit) { ?>
           	<tr>
            <td colspan="4">
            <div class="col-xs-12"><div class="dataTables_info" id="dynamic-table_info" role="status" aria-live="polite">Showing <?php echo ($start+1); ?> to <?php echo ($start+$limit > $total_pages) ? $total_pages : $start+$limit; ?> of <?php echo $total_pages; ?> entries</div></div>
            </td>
					<td align="center" colspan="8" class="inactive"><div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
            <ul class="pagination">
              <?php paginate($start,$limit,$total_pages,$filePath,$otherParams); ?>
            </ul>
            </div></td>
				  </tr>
														 <?php } ?>
				  
				     </tbody>
                                                        </table>
            <?php
			
		 	 $query_graph = dbq("SELECT *,c.cust_name,j.j_code from inw_dispatch_history as wd, adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  $ccc  $search $search2 $search4 ");
			 while($res_graph=$query_graph->fetch_array())
			 {
				 
				 
				$stage_gr=preg_replace("/REV([0-9]+)/","REV",$res_graph['stage']);
				$stage_gi=preg_replace("/ISSCOR([0-9]+)/","ISSCOR",$res_graph['stage']);
																		
		
		//$query_dispgraph = dbq_row("SELECT * FROM `inw_dispatch_history` WHERE a_id ='" . $res_graph['id'] . "' ORDER BY `idh_id` DESC limit 1 ");
		$query_dispgraph = dbq_row("SELECT * FROM `inw_inward_dtl` WHERE id ='" . $res_graph['a_id'] . "'");

         $send_dts = date('d-m-Y', strtotime($res_graph['sent_date']));
		 
		 	if($res_graph['stage'] == "FP")
		{
			$due_dt = (date('d-m-Y',strtotime($query_dispgraph['due_dt'])));
		}
		elseif($stage_gr=='REV')
		{
			$rev_rep=str_replace("REV","",$res_graph['stage']);
			$query = "SELECT * FROM inw_revisions_dtl WHERE inw_id = '".$res_graph['a_id']."' and revision_count='".$rev_rep."'";
		    $revin = dbq($query);
		    $revinfo = $revin->fetch_assoc();
			$due_dt = (date('d-m-Y',strtotime($revinfo['due_date'])));
			
		}
		else
		{
			
			$due_dt = (date('d-m-Y',strtotime($query_dispgraph['due_dt'])));
			
		}
				 
																				if ($res_graph['stage'] == 'FP') {
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																			
                                                                                <?php
                                                                            } elseif($stage_gr=='REV') {
																				
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																				
                                                                                <?php
                                                                            }
																			elseif($stage_gi=='ISSCOR') {
																				
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																				
                                                                                <?php
                                                                            }
																			else{
																				
																				$due_dts=date('d-m-Y', strtotime($due_dt));
                                                                                ?>
																			
                                                                                <?php
                                                                            }
				 
				
																
																
																  if (strtotime($send_dts) == strtotime($due_dts)) {
                                                                    $lg++;

																	
                                                                } elseif (strtotime($send_dts) > strtotime($due_dts)) {
                                                                    $jg++;
                                                                } elseif (strtotime($send_dts) < strtotime($due_dts)) {
                                                                    $kg++;
                                                                }
																	
			} 
																	$ahead=$kg;
																	$delay=$jg;
																	$ontime=$lg;
																	 
			?>
			
			

                                                    </div>  
													
													

													
													<!-- Styles -->
<style>
#chartdiv {
  width: 100%;
  height: 500px;
}
#chartdiv1 {
  width: 100%;
  height: 500px;
}#chartdiv2 {
  width: 100%;
  height: 500px;
}
</style>

<!-- Resources -->
<script src="https://www.amcharts.com/lib/4/core.js"></script>
<script src="https://www.amcharts.com/lib/4/charts.js"></script>
<script src="https://www.amcharts.com/lib/4/themes/animated.js"></script>

<!-- Chart code -->
<script>
am4core.ready(function() {
if (!document.getElementById("chartdiv")) { return; }

// Themes begin
am4core.useTheme(am4themes_animated);
// Themes end

// Create chart instance
var chart = am4core.create("chartdiv", am4charts.XYChart);

// Add data
chart.data = [{
  "Performance": "Ahead",
  "Counts": <?php if($ahead!=0){ echo $ahead; }else{ echo "0"; } ?>
}, {
  "Performance": "Ontime",
  "Counts": <?php if($ontime!=0){ echo $ontime; }else{ echo "0"; } ?>
}, {
  "Performance": "Delay",
  "Counts": <?php if($delay!=0){ echo $delay; }else{ echo "0"; } ?>



}];

// Create axes

var categoryAxis = chart.xAxes.push(new am4charts.CategoryAxis());
categoryAxis.dataFields.category = "Performance";
categoryAxis.renderer.grid.template.location = 0;
categoryAxis.renderer.minGridDistance = 15;

categoryAxis.renderer.labels.template.adapter.add("dy", function(dy, target) {
  if (target.dataItem && target.dataItem.index & 2 == 2) {
    return dy + 15;
  }
  return dy;
});

var valueAxis = chart.yAxes.push(new am4charts.ValueAxis());

// Create series
var series = chart.series.push(new am4charts.ColumnSeries());
series.dataFields.valueY = "Counts";
series.dataFields.categoryX = "Performance";
series.name = "Counts";
series.columns.template.tooltipText = "{categoryX}: [bold]{valueY}[/]";
series.columns.template.fillOpacity = .8;

var columnTemplate = series.columns.template;
columnTemplate.strokeWidth = 2;
columnTemplate.strokeOpacity = 1;

}); // end am4core.ready()


am4core.ready(function() {
if (!document.getElementById("chartdiv1")) { return; }

// Themes begin
am4core.useTheme(am4themes_animated);
// Themes end

// Create chart instance
var chart1 = am4core.create("chartdiv1", am4charts.XYChart);

// Add data
chart1.data = [{
  "Performance": "Ahead",
  "Counts": <?php if($ahead!=0){ echo $ahead; }else{ echo "0"; } ?>
}, {
  "Performance": "Ontime",
  "Counts": <?php if($ontime!=0){ echo $ontime; }else{ echo "0"; } ?>
}, {
  "Performance": "Delay",
  "Counts": <?php if($delay!=0){ echo $delay; }else{ echo "0"; } ?>


}];

// Create axes

var categoryAxis = chart1.xAxes.push(new am4charts.CategoryAxis());
categoryAxis.dataFields.category = "Performance";
categoryAxis.renderer.grid.template.location = 0;
categoryAxis.renderer.minGridDistance = 15;

categoryAxis.renderer.labels.template.adapter.add("dy", function(dy, target) {
  if (target.dataItem && target.dataItem.index & 2 == 2) {
    return dy + 15;
  }
  return dy;
});

var valueAxis = chart1.yAxes.push(new am4charts.ValueAxis());

// Create series
var series = chart1.series.push(new am4charts.ColumnSeries());
series.dataFields.valueY = "Counts";
series.dataFields.categoryX = "Performance";
series.name = "Counts";
series.columns.template.tooltipText = "{categoryX}: [bold]{valueY}[/]";
series.columns.template.fillOpacity = .8;

var columnTemplate = series.columns.template;
columnTemplate.strokeWidth = 2;
columnTemplate.strokeOpacity = 1;

}); // end am4core.ready()



am4core.ready(function() {
if (!document.getElementById("chartdiv2")) { return; }

// Themes begin
am4core.useTheme(am4themes_animated);
// Themes end

// Create chart instance
var chart2 = am4core.create("chartdiv2", am4charts.XYChart);

// Add data
chart2.data = [{
  "Performance": "Ahead",
  "Counts": <?php if($ahead!=0){ echo $ahead; }else{ echo "0"; } ?>
}, {
  "Performance": "Ontime",
  "Counts": <?php if($ontime!=0){ echo $ontime; }else{ echo "0"; } ?>
}, {
  "Performance": "Delay",
  "Counts": <?php if($delay!=0){ echo $delay; }else{ echo "0"; } ?>



}];

// Create axes

var categoryAxis = chart2.xAxes.push(new am4charts.CategoryAxis());
categoryAxis.dataFields.category = "Performance";
categoryAxis.renderer.grid.template.location = 0;
categoryAxis.renderer.minGridDistance = 15;

categoryAxis.renderer.labels.template.adapter.add("dy", function(dy, target) {
  if (target.dataItem && target.dataItem.index & 2 == 2) {
    return dy + 15;
  }
  return dy;
});

var valueAxis = chart2.yAxes.push(new am4charts.ValueAxis());

// Create series
var series = chart2.series.push(new am4charts.ColumnSeries());
series.dataFields.valueY = "Counts";
series.dataFields.categoryX = "Performance";
series.name = "Counts";
series.columns.template.tooltipText = "{categoryX}: [bold]{valueY}[/]";
series.columns.template.fillOpacity = .8;

var columnTemplate = series.columns.template;
columnTemplate.strokeWidth = 2;
columnTemplate.strokeOpacity = 1;

}); // end am4core.ready()
</script>

<!-- HTML -->
										
													 <?php }  ?>
													

														 <?php
													//echo h($_REQUEST['radio']);
													if ($_REQUEST['radio']=='dr') {
                                                        $query = "SELECT c.cust_name,journal_title,pub_id,recv_dt,due_dt,highpriority,wd.created_dt,fig,tab,status,approved_by,wd.id,j.j_code,manuscript_count,stage,r.received_date,r.due_date,r.correction_pages,r.revision_count FROM `inw_inward_dtl` as wd LEFT JOIN inw_revisions_dtl as r ON (wd.`id` =r.`inw_id` and r.r_id = (select r_id from inw_revisions_dtl where inw_id = wd.`id` order by r_id DESC limit 1)), adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  AND wd.status IN ('Client Review') and wd.v_issue_id <= 0 $ccc  $search1 $search2 $search4 ";
														
														$cnt_query = "SELECT COUNT(wd.id) as num,c.cust_name,journal_title,pub_id,recv_dt,due_dt,highpriority,wd.created_dt,fig,tab,status,approved_by,wd.id,j.j_code,manuscript_count,stage,r.received_date,r.due_date,r.correction_pages,r.revision_count FROM `inw_inward_dtl` as wd LEFT JOIN inw_revisions_dtl as r ON (wd.`id` =r.`inw_id` and r.r_id = (select r_id from inw_revisions_dtl where inw_id = wd.`id` order by r_id DESC limit 1)), adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id AND wd.status IN ('Client Review') and wd.v_issue_id <= 0 $ccc  $search1 $search2 $search4";
														
															$customers = dbq($cnt_query);
													$page_count = $customers->fetch_assoc();
													$total_pages = isset($page_count['num']) ? (int) $page_count['num'] : 0;
													$order_by = "ORDER BY `id` DESC";
													$start = max(0, (int) $_REQUEST['start']);
		
										$filePath = $self . '?page=1&' . msr_qs($filePathKeys);
													$limit =10; //how many items to show per page
													
													$query = $query. $order_by." LIMIT $start, $limit";  
													//echo $query;
													$articles = dbq($query);	
                                                    $articles->num_rows;
													//echo "fdgfdg".$total_pages;
                                                   
													
													

												
                                                    ?>   
														<div class="page-header">
                                                            <h1>Detailed Report</h1>
                                                        </div>
                                                    <div class="myTable1">

                                                        <table class="table table-striped table-bordered table-hover">
                                                            <thead>
                                                            <th>Client </th>
                                                            <th>Journal Name</th>
                                                                                                                        <th>FileName</th>
                                                            <th>Stage</th>
                                                            <th>Received Date</th>
                                                            <th>Due Date</th>
															<th>Dispatched Date</th>
                                                            <th>Schedule</th>    
															<th>Job Card</th>    			
												</thead>
                                                            <tbody id="tbl1Body">
                                                                <?php
                                                                if ($articles->num_rows > 0) {
                                                                    ?>
                                                                    <?php
                                                                    while ($row_history = $articles->fetch_assoc()) {
																		
																		$stage_r=preg_replace("/REV([0-9]+)/","REV",$row_history['stage']);
																		$stage_i=preg_replace("/ISSCOR([0-9]+)/","ISSCOR",$row_history['stage']);
                                                                        //print_r($row_history);
																		
																		$trans_sql = "SELECT u.full_name,t.current_status,t.id FROM `inw_transactions` as t,users as u WHERE t.process_user = u.id AND t.`project_id` = '".$row_history['id']."'  AND t.current_status NOT IN ('Completed','Take Over') AND t.stage = '".$row_history['stage']."' AND ('Completed' != (select current_status from `inw_transactions` where `project_id` = '".$row_history['id']."' ORDER BY `id` DESC limit 1) )  ORDER BY t.`id` DESC limit 1;";
		//echo $trans_sql.'<br>';		
		
		$query_disp = dbq_row("SELECT * FROM `inw_dispatch_history` WHERE a_id ='" . $row_history['id'] . "' ORDER BY `idh_id` DESC limit 1 ");
																			$chk_transaction = dbq($trans_sql); 
																			$transaction_toatl_count=$chk_transaction->num_rows;
																			$trans_res = $chk_transaction->fetch_assoc();	
                                                                        $send_dt = date('d-m-Y', strtotime($query_disp['sent_date']));
                                                                        $send_dts = date('d-m-Y', strtotime($query_disp['sent_date']));
                                                                        ?>
                                                                        <tr>
                                                                            <td><?php echo h($row_history['cust_name']) ?></td>
                                                                            <td><?php echo h($row_history['j_code']) ?></td>
                                                                            
                                                                            <td><?php echo h($row_history['pub_id']) ?></td>
                                                                            
                                                                                <?php
                                                                            if ($row_history['stage'] == 'FP') {
																				$due_dts=date('d-m-Y', strtotime($row_history['due_dt']));
                                                                                ?>
																				<td><?php echo h($row_history['stage']); ?></td>
                                                                                <td><?php echo fmt_dt($row_history['recv_dt']) ?></td>
                                                                                <td><?php echo fmt_dt($row_history['due_dt']) ?></td>
                                                                                <?php
                                                                            } elseif($stage_r=='REV') {
																				
																				$due_dts=date('d-m-Y', strtotime($row_history['due_date']));
                                                                                ?>
																				 <td><?php echo  "REV" . $row_history['revision_count'] ?></td>

                                                                                <td><?php echo fmt_dt($row_history['received_date']) ?></td>

                                                                                <td><?php echo fmt_dt($row_history['due_date']) ?></td>
                                                                                <?php
                                                                            }
																			elseif($stage_i=='ISSCOR') {
																				
																				$due_dts=date('d-m-Y', strtotime($row_history['due_dt']));
                                                                                ?>
																				 <td><?php echo h($row_history['stage']) ?></td>
                                                                                <td><?php echo fmt_dt($row_history['created_dt']); ?></td>

                                                                                <td><?php echo fmt_dt($row_history['due_dt']); ?></td>
                                                                                <?php
                                                                            }
																			else{
																				
																				$due_dts=date('d-m-Y', strtotime($row_history['due_dt']));
                                                                                ?>
																				 <td><?php echo h($row_history['stage']) ?></td>
                                                                                <td><?php echo fmt_dt($row_history['created_dt']) ?></td>

                                                                                <td><?php echo fmt_dt($row_history['due_dt']) ?></td>
                                                                                <?php
                                                                            }
																			
                                                                            ?>
                                                                             <td><?php echo $send_dt; ?></td>
                                                                            <td>	<?php
                                                                if (strtotime($send_dts) == strtotime($due_dts)) {
                                                                    $l++;

                                                                    echo "On Time";
                                                                } elseif (strtotime($send_dts) > strtotime($due_dts)) {
                                                                    $j++;
                                                                    echo "Delay";
                                                                } elseif (strtotime($send_dts) < strtotime($due_dts)) {
                                                                    $k++;
                                                                    echo "Ahead";
                                                                }
                                                                        ?>
                                                                            </td>

                    <td><a title="Job Card" href="job_card.php?id=<?php echo $row_history['id']; ?>" target="_blank" class="btn btn-xs btn-info"><i class="ace-icon fa fa-clock-o bigger-120"></i></a> </td> 
                                                                        </tr>
                                                                        <?php
                                                                        $i++;
                                                                    }
                                                                }
                                                                ?>
                                                            <?php  if($total_pages > $limit) { ?>
           	<tr>
            <td colspan="4">
            <div class="col-xs-12"><div class="dataTables_info" id="dynamic-table_info" role="status" aria-live="polite">Showing <?php echo ($start+1); ?> to <?php echo ($start+$limit > $total_pages) ? $total_pages : $start+$limit; ?> of <?php echo $total_pages; ?> entries</div></div>
            </td>
					<td align="center" colspan="8" class="inactive"><div class="dataTables_paginate paging_simple_numbers" id="datatable_paginate">
            <ul class="pagination">
              <?php paginate($start,$limit,$total_pages,$filePath,$otherParams); ?>
            </ul>
            </div></td>
				  </tr>
            <?php }  ?>
</tbody>
</table>
                                                    </div>  
													
													 <?php }  ?>
                                                    </div> 
													
		<?php if ($_REQUEST['radio']=='cr') {?>											            
<div id="consolidated-report-tables" >
                                                        <div class="page-header">
                                                            <h1>Consolidated-Report</h1>
                                                        </div>

                                                        <table id="consolidated-report-table" class="table table-striped table-bordered table-hover " style="width: 70%; margin-top: 30px; margin-left: 160px;">
                                                            <thead>
                                                                <tr>
                                                                    <th rowspan="2">Customer</th>
                                                                    <th rowspan="2">Journal</th>
                                                                    <th colspan="3">Stages</th>
                                                                </tr>
                                                                <tr>
                                                                    <th>FP</th>
                                                                    <th>REV</th>
                                                                    <th>FIN</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
<?php
    if($_REQUEST['cust_id']!="" && $_REQUEST['cust_id']!="all")
	{
    $sql_query = "SELECT * FROM `adm_customer_master` where id='".esc($_REQUEST['cust_id'])."' ";
	}
	else
	{
    $sql_query = "SELECT * FROM `adm_customer_master`";
	}
    $run_row = dbq($sql_query);
    while ($row = $run_row->fetch_assoc()) {

	if($_REQUEST['j_id']!="" && $_REQUEST['j_id']!="all")
	{
        $sql_journal = "SELECT `j_id`, `j_cust_id`,`j_code` FROM `adm_journals` WHERE  j_cust_id =".$row['id']." and j_id='".esc($_REQUEST['j_id'])."' ";
	}
	elseif($_REQUEST['dp_platform']!="")
	{
        $sql_journal = "SELECT `j_id`, `j_cust_id`,`j_code` FROM `adm_journals` WHERE  j_cust_id =".$row['id']." and j_platform='".esc($_REQUEST['dp_platform'])."'";
	}
		else
	{
        $sql_journal = "SELECT `j_id`, `j_cust_id`,`j_code` FROM `adm_journals` WHERE  j_cust_id =".$row['id']."";
	}	
		
        $row_run = dbq($sql_journal);
        $num_row = $row_run->num_rows;
        $r = 1;
		?>
		  <tr>
           <td rowspan="<?php echo max(1, (int) $num_row); ?>"><?php echo h($row['cust_name']); ?> </td>
		<?php 
		$fp_id_count=0;
		$fev_id_count=0;
		$fin_id_count=0;
        while ($row_journal = $row_run->fetch_assoc()) {
            // print_r($row_journal);
			if($_REQUEST['j_id']!="" && $_REQUEST['j_id']!="all")
			{
            $j_id = (int) $_REQUEST['j_id'];
			}
			else
			{
			$j_id = (int) $row_journal['j_id'];
			}
			$cust_id = (int) $row['id'];
			
            $j_codes = $row_journal['j_code'];
			

			
              $fp_query = "SELECT count(idh_id)as stage_count FROM `inw_dispatch_history` as wd,adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  AND stage = 'FP' AND wd.cust_id = $cust_id    AND wd.j_id = $j_id    $search $search2 $search4 ";
            $fp_run_query = dbq($fp_query);
            $fp_row = $fp_run_query->fetch_assoc();
            $fp_id_count = $fp_row['stage_count'];
            $tfp_id_count+= $fp_id_count;

            $rev_query = "SELECT count(idh_id)as rev_count FROM `inw_dispatch_history`as wd,adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  AND  stage LIKE 'REV%' AND wd.cust_id = $cust_id AND wd.j_id = $j_id    $search $search2 $search4";

            $fev_run_query = dbq($rev_query);
            $fev_row = $fev_run_query->fetch_assoc();
            $fev_id_count = $fev_row['rev_count'];
            $tfev_id_count+= $fev_id_count;

            $fin_query = "SELECT count(idh_id)as id FROM `inw_dispatch_history` as wd,adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id  AND  stage LIKE 'FIN%' AND wd.cust_id = $cust_id and  wd.j_id = $j_id   $search $search2 $search4";

            $fin_run_query = dbq($fin_query);
            $fin_row = $fin_run_query->fetch_assoc();
            $fin_id_count = $fin_row['id'];
            $tfin_id_count+= $fin_id_count;
		 

            if ($r == 1) {
                ?>
                                                                              
                                                                                    <td><?php echo h($j_codes); ?></td>
                                                                                    <td><?php echo  $fp_id_count; ?></td>
                                                                                    <td><?php echo  $fev_id_count; ?></td>
                                                                                    <td><?php echo  $fin_id_count; ?></td>
                                                                                </tr>
                <?php
              
            } else {
                ?>
                                                                                <tr>
                                                                                    <td><?php echo h($j_codes); ?></td>
                                                                                    <td><?php echo  $fp_id_count; ?></td>
                                                                                    <td><?php echo  $fev_id_count; ?></td>
                                                                                    <td><?php echo  $fin_id_count; ?></td>
                                                                                </tr>
                <?php
            }
			  $r++;
        }
		
    if ($num_row == 0) { echo '<td colspan="4">-</td></tr>'; }
    }
//}
?>

																					<tr>
                                                                                    <td></td>
                                                                                    <td><b style="color:red;">Total</b></td>
                                                                                    <td><b style="color:red;"><?php echo  $tfp_id_count; ?></b></td>
                                                                                    <td><b style="color:red;"><?php echo  $tfev_id_count; ?></b></td>
                                                                                    <td><b style="color:red;"><?php echo  $tfin_id_count; ?></b></td>
                                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    <?php if (($tfp_id_count + $tfev_id_count + $tfin_id_count) == 0) { ?>
<div class="alert alert-warning" style="margin:15px 0 0 160px;width:70%;">No records found. <b>This Consolidated report still reads the journal dispatch tables</b> (<code>inw_dispatch_history</code> / <code>adm_journals</code>), not the book tables, so it stays empty until it is pointed at the book dispatch table. Run <code>debug_master_status_report.php</code> (section 4) to check.</div>
<?php } ?>
</div>
													<?php }?>
                                                  
												  
												  <?php if ($_REQUEST['radio']=='ce') {
													  
$fds=date('d-m-Y', strtotime('-3 days'));
$tds=date('d-m-Y');
$ce_yday=date('Y-m-d', strtotime('-1 days'));
$ce_tom=date('Y-m-d', strtotime('+1 days'));
	if($_REQUEST['cfrom_dt']!="")
{
$start_date =date('Y-m-d',strtotime($_REQUEST['cfrom_dt']));
$end_date =date('Y-m-d',strtotime($_REQUEST['cto_dt']));
}
else
{
$start_date =$fds;
$end_date =$tds;	
}
	
?>											            
<div id="cepe-report-tables" >
                                                        <div class="page-header">
                                                            <h1>CE/PE-Report</h1>
                                                        </div>

		
												<div >
                                                
																
                                                        <table class="table table-striped table-bordered table-hover " style="width: 50%; margin-top: 30px; margin-left: 160px;">
														
														    
															
                                                            <thead>
															
                                                                <tr>
                                                                    <th rowspan="2">User</th>
                                                                    <th colspan="18" style="width:90%;color:red;"> FP Stage - <?php echo date('d-M-Y',strtotime($start_date));?> TO <?php echo date('d-M -Y',strtotime($end_date));?></th>
                                                                </tr>
															
                                                             <tr>
<?php 

while (strtotime($start_date) <= strtotime($end_date))
{

	
	if($start_date==$ce_yday)
	{
		$today='Yesterday';
	}
	elseif($start_date==date('Y-m-d'))
	{
		$today='Today';
	}
	elseif($start_date==$ce_tom)
	{
		$today='Tom';
	}
	else
	{
	$today= date('d M',strtotime($start_date));	
	}
?>
<th style="width:200px;color:red;font-size:18px;"> <?php echo $today;?></th>
<?php 

 $start_date = date ("Y-m-d", strtotime("+1 days", strtotime($start_date)));
}
?>
																	
                                                                </tr>
                                                            </thead>
                                                            <tbody>
		 
		<?php
                                                      if($_REQUEST['cfrom_dt']!="")
{
  
     $sql_query = "SELECT user_id FROM `assigned_article_user` as aau, users as u where aau.user_id = u.id and u.approved = 1 and u.department_id IN (1,2) GROUP BY user_id";
    $run_row = dbq($sql_query);
    while ($row = $run_row->fetch_assoc()) {


         $sql_users = "SELECT * FROM `users` WHERE approved = 1 AND  id ='".$row['user_id']."'";
		
		
        $row_run = dbq($sql_users);
        $num_row = $row_run->num_rows;
        $r = 1;
		 
        $row_users = $row_run->fetch_assoc(); 			
			$user_id = $row_users['id'];
            $full_name = $row_users['full_name'];
           
			// if ($r == 1) {
                ?> <tr>
                                                                              
                                                                                    <td style="width:25% !important;"><?php echo h($full_name); ?></td>
																		<?php 
																		
if($_REQUEST['cfrom_dt']!="")
{
 $sstart_date =date('Y-m-d',strtotime($_REQUEST['cfrom_dt']));
 $send_date =date('Y-m-d',strtotime($_REQUEST['cto_dt']));
}
else
{
$sstart_date =$fds;
$send_date =$tds;	
}

					 	while (strtotime($sstart_date) <= strtotime($send_date))
						{
	
			
               $ce_day = date('Y-m-d', strtotime($sstart_date));
				$fp_row = dbq("SELECT COUNT(aau_id) AS total_count, SUM(CASE WHEN article_ids IS NULL OR article_ids IN ('','Null') THEN 0 ELSE 1 END) AS pending_count FROM `assigned_article_user` WHERE DATE_FORMAT(a_date,'%Y-%m-%d')='".$ce_day."' AND user_id='".(int)$row_users['id']."'")->fetch_assoc();
				echo '<td>';
				if ($ce_day == $ce_yday)
				{
					if ((int)$fp_row['total_count'] == 0)
					{
						echo 'Not Alloted';
					}
					elseif ((int)$fp_row['pending_count'] == 0)
					{
						echo 'Done';
					}
					else
					{
						echo '<a href="pending-list.php?uid='.(int)$row_users['id'].'&pdate='.urlencode($ce_day).'" target="_blank">Pending</a>';
					}
				}
				else
				{
					$cnt_row = dbq("SELECT COUNT(wd.id) as num FROM `inw_inward_dtl` as wd, adm_customer_master as c, adm_journals as j WHERE wd.cust_id = c.id and wd.j_id = j.j_id and assigned_user_id='".(int)$row_users['id']."' and DATE_FORMAT(ce_pe_due_date,'%Y-%m-%d')='".$ce_day."' ")->fetch_assoc();
					echo '<a href="assigned-work-list.php?uid='.(int)$row_users['id'].'&pdate='.urlencode($ce_day).'" target="_blank">'.(int)$cnt_row['num'].'</a>';
				}
				echo '</td>';
				$sstart_date = date ("Y-m-d", strtotime("+1 days", strtotime($sstart_date)));


						} 
						?>
          
			
                                                                                   
                                                                                </tr>
                <?php
              
           // } 
			  $r++;
       // }
		
		?>
				
		<?php
    }
}
?>

		   </tbody>
                                                        </table>
														

                                                    </div>	
													<?php } ?>
                                                    </div>
													
													
                                                </div>
                                            </div>
                                        </div>
                                    </div>
									
									
                                    <!-- /.widget-main --> 
                                </div>
                                <!-- /.widget-body --> 
                            </div>
                            <!-- PAGE CONTENT ENDS --> 
                        </div>
                        <!-- /.col --> 
                    </div>
					

	
                    <!-- /.row --> 
                </div>
				
				
                <!-- /.page-content --> 
            </div>
        </div>
		<div class="container"> 
  	<?php 
if($_REQUEST['radio']=='dp')
{
?>	
<div style="margin-left:50px;width:100%; padding-bottom:100px;">

	<?php
	if($_REQUEST['date_wise']=='m')
	{
	?>
<div style="float:left;">

<b style="color:red;">Delivery Performance of the month - <?php if($_REQUEST['date_wise']!= ""){ echo h($_REQUEST['mfrom_dt']);}else{ echo date('M'); }?> </b>


<div id="chartdiv" style="width:100% !important;height:200px;">
</div>
</div>
<?php 
	}
	?>
	<?php
	if($_REQUEST['date_wise']=='d')
	{
	?>
<div style="float:left;margin-left:150px;">
<b style="color:red;">Delivery Performance of the day - <?php if($_REQUEST['date_wise']!= ""){ echo "From: ".h($_REQUEST['from_dt']); echo " To :".h($_REQUEST['to_dt']);}else{ echo date('d-m-Y');}?></b>


<div id="chartdiv1" style="width:100% !important;height:200px;" >
</div>
</div>
<?php 
	}
	?>
	<?php
	if($_REQUEST['date_wise']=='y')
	{
	?>
<div style="float:left;margin-left:150px;">
<b style="color:red;"> Delivery Performance of the year - <?php if($_REQUEST['date_wise']!= ""){ echo date('Y',strtotime($_REQUEST['yfrom_dt']));}else{ echo date('Y');}?></b>

<div id="chartdiv2" style="width:100% !important;height:200px;">
</div>
</div>
<?php 
	}
	?>
	</div>	
	<?php 
}
?>
  <!-- Modal -->
  <div class="modal fade" id="myModal" role="dialog">
    <div class="modal-dialog">    
      <!-- Modal content-->
      <div class="modal-content">        
        <div class="modal-body">		
          <p id="ppopup"></p>
        </div>        
      </div>
      
    </div>
  </div>
  
</div>
		
        <!-- /.main-content -->
<div style="padding-bottom:200px;">

<?php include("includes/footer.php"); ?>
        <a href="#" id="btn-scroll-up" class="btn-scroll-up btn btn-sm btn-inverse"> <i class="ace-icon fa fa-angle-double-up icon-only bigger-110"></i> </a>
		</div>
</div>
    <!-- /.main-container --> 

    <!-- basic scripts --> 

    <!--[if !IE]> -->
    <script src="assets/js/jquery-2.1.4.min.js"></script>

    <!-- <![endif]--> 

    <!--[if IE]>
    <script src="assets/js/jquery-1.11.3.min.js"></script>
    <![endif]--> 
    <script type="text/javascript">
                                                            if ('ontouchstart' in document.documentElement)
                                                                document.write("<script src='assets/js/jquery.mobile.custom.min.js'>" + "<" + "/script>");
    </script> 
    <script src="assets/js/bootstrap.min.js"></script> 

    <!-- page specific plugin scripts --> 
    <script src="assets/js/wizard.min.js"></script> 
    <script src="assets/js/jquery.validate.min.js"></script> 
    <script src="assets/js/jquery-additional-methods.min.js"></script> 
    <script src="assets/js/bootbox.js"></script> 
    <script src="assets/js/jquery.maskedinput.min.js"></script> 
    <script src="assets/js/select2.min.js"></script> 
    <script src="assets/js/bootstrap-datepicker.min.js"></script>

    <!-- ace scripts --> 
    <script src="assets/js/ace-elements.min.js"></script> 
    <script src="assets/js/ace.min.js"></script> 

    <!-- inline scripts related to this page --> 
    <script type="text/javascript">
                                                            jQuery(function ($) {

                                                                //documentation : http://docs.jquery.com/Plugins/Validation/validate

                                                                //datepicker plugin
                                                                //link
                                                                $('.date-picker').datepicker({
                                                                    autoclose: true,
                                                                    todayHighlight: true,
                                                                    format: 'dd-mm-yyyy'
                                                                })

                                                                $('#validation-form').validate({errorClass: 'help-block'});

                                                            })
    </script>
    
    <script src="assets/js/jquery.dataTables.min.js"></script> 
    <script src="assets/js/jquery.dataTables.bootstrap.min.js"></script> 
    <script src="assets/js/dataTables.buttons.min.js"></script> 
    <script src="assets/js/buttons.flash.min.js"></script> 
    <script src="assets/js/buttons.html5.min.js"></script> 
    <script src="assets/js/buttons.print.min.js"></script> 
    <script src="assets/js/buttons.colVis.min.js"></script> 
    <script src="assets/js/dataTables.select.min.js"></script> 


     

    <script src="assets/js/highcharts.js"></script>
    <script src="assets/js/highcharts-3d.js"></script>
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.5.2/css/buttons.dataTables.min.css" />
    <!-- inline scripts related to this page --> 
    <script type="text/javascript">

        $(document).ready(function () {
            $('#dynamic-table').DataTable({
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],

                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });

        });
    </script>
    <script type="text/javascript">
        $(document).ready(function () {
            $('#dynamic-table1').DataTable({
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],

                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });

        });
    </script>
    <script type="text/javascript">
        $(document).ready(function () {
            $('#dynamic-table2').DataTable({
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],

                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });

        });
    </script>
    <script type="text/javascript">
        $(document).ready(function () {
            $('#dynamic-table3').DataTable({
                "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],

                dom: 'Bfrtip',
                buttons: [
                    'copy', 'csv', 'excel', 'pdf', 'print'
                ]
            });

        });
    </script>
    <script>
        // which form groups are visible for each report type (1 = show, 0 = hide)
        var msrLayout = {
            wip: {customer_select: 1, stage_cons: 1, dept_wip: 1, platform_dp: 0, status_wip: 1, wip_nodate: 0, date_sdf: 0, cepe_fsdf: 0, cepe_tsdf: 0},
            sfd: {customer_select: 1, stage_cons: 1, dept_wip: 0, platform_dp: 0, status_wip: 0, wip_nodate: 0, date_sdf: 1, cepe_fsdf: 0, cepe_tsdf: 0},
            dp: {customer_select: 1, stage_cons: 1, dept_wip: 0, platform_dp: 1, status_wip: 0, wip_nodate: 1, date_sdf: 0, cepe_fsdf: 0, cepe_tsdf: 0},
            dr: {customer_select: 1, stage_cons: 1, dept_wip: 0, platform_dp: 0, status_wip: 0, wip_nodate: 1, date_sdf: 0, cepe_fsdf: 0, cepe_tsdf: 0},
            cr: {customer_select: 1, stage_cons: 0, dept_wip: 0, platform_dp: 1, status_wip: 0, wip_nodate: 1, date_sdf: 0, cepe_fsdf: 0, cepe_tsdf: 0},
            ce: {customer_select: 0, stage_cons: 0, dept_wip: 0, platform_dp: 0, status_wip: 0, wip_nodate: 0, date_sdf: 0, cepe_fsdf: 1, cepe_tsdf: 1}
        };
        var msrInitialRadio = <?php echo json_encode($_REQUEST['radio']); ?>;

        function msrApplyRadio(val) {
            $("#radio_check").val(val || '');
            var cfg = msrLayout[val];
            if (!cfg) {
                return;
            }
            $.each(cfg, function (id, show) {
                $("#" + id).css("display", show ? "block" : "none");
            });
        }

        function checkvalidate() {
            if ($("#radio_check").val() == '') {
                alert('Please Select Master Status');
                return false;
            }
            return true;
        }

        $(document).ready(function () {
            function pad(n) {
                return (n < 10 ? '0' : '') + n;
            }

            // one handler for every report radio (the old per-radio attr("checked") toggling broke
            // as soon as a radio was selected a second time)
            $('input[name="radio"]').on('change', function () {
                msrApplyRadio(this.value);
            });
            msrApplyRadio($('input[name="radio"]:checked').val() || msrInitialRadio);

            $("#yearwise").click(function () {
                $("#year_div").show();
                $("#yfrom_dt").val(<?php echo date('Y'); ?>);
                $("#month_div").hide();
                $("#mfrom_dt").val('');
                $("#day_div").hide();
                $("#from_dt").val('');
                $("#to_dt").val('');
            });
            $("#monthwise").click(function () {
                var date = new Date();
                $("#month_div").show();
                $("#year_div").hide();
                $("#day_div").hide();
                $("#yfrom_dt").val('');
                $("#from_dt").val('');
                $("#to_dt").val('');
                $("#mfrom_dt").val(pad(date.getMonth() + 1) + "-" + date.getFullYear()); // mm-yyyy, matches DATE_FORMAT '%m-%Y'
            });
            $("#daywise").click(function () {
                var date = new Date();
                var vals = pad(date.getDate()) + "-" + pad(date.getMonth() + 1) + "-" + date.getFullYear();
                $("#year_div").hide();
                $("#yfrom_dt").val('');
                $("#month_div").hide();
                $("#mfrom_dt").val('');
                $("#day_div").show();
                $("#from_dt").val(vals);
                $("#to_dt").val(vals);
            });

            $(".fydatepicker").datepicker({
                format: "yyyy",
                viewMode: "years",
                minViewMode: "years"
            });
            $(".mydatepicker").datepicker({
                format: "mm-yyyy",
                viewMode: "months",
                minViewMode: "months"
            });
        });
    </script>
    <script type="text/javascript">

        //console.log(<?php echo  json_encode($k); ?>);
        Highcharts.setOptions({
            colors: ['#2091CF', '#AF4E96', '#910000']
        });
        if (document.getElementById('container-chart')) Highcharts.chart('container-chart', {
            chart: {
                plotBackgroundColor: null,
                plotBorderWidth: null,
                plotShadow: false,
                type: 'pie'
            },
            title: {
                text: 'Articles Delivery Schedule'

            },
            credits: {
                enabled: false
            },
            tooltip: {
                pointFormat: '{series.name}: <b>{point.percentage:.1f}%</b>'
            },
            plotOptions: {
                pie: {
                    allowPointSelect: true,
                    cursor: 'pointer',
                    dataLabels: {
                        enabled: true,
                        format: '<b>{point.name}</b>: {point.percentage:.1f} %',
                        style: {
                            color: (Highcharts.theme && Highcharts.theme.contrastTextColor) || 'black'
                        }
                    }
                }
            },
            series: [{
                    name: 'Articles',
                    colorByPoint: true,
                    data: [{
                            name: 'Ahead',
                            y:<?php echo  json_encode($k); ?>
                        }, {
                            name: 'On Time',
                            y: <?php echo  json_encode($l); ?>
                        }, {
                            name: 'Delay',
                            y: <?php echo  json_encode($j); ?>
                        }]
                }]
        });
		
			function timepopup(id)
			{				
				var url = "project_time.php?id="+id;
				$("#ppopup").load(url);
			}
    </script>
<?php msr_debug_panel(); ?>
</body>
</html>

