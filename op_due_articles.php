<?php
/*
 * Conversion - Schedule mail (due articles / books).
 *
 * Builds the schedule tables (one-time projects + conversion books, per customer) and e-mails them
 * using the recipients stored in adm_schedule_mail_details (type = WIP_Schedule_mail_details).
 *
 * Run from cron / a wrapper. The wrapper may already provide $db (mysqli) and the PHPMailer class;
 * if not, dbc.php and the bundled PHPMailer are loaded here.
 *
 * Test without sending anything (CLI only):   php op_due_articles.php --dry-run
 */
date_default_timezone_set('Asia/Kolkata');

$opd_own_db = false;
if (!isset($db) || !($db instanceof mysqli)) {
    include (__DIR__ . '/dbc.php');
    $opd_own_db = true;
}
if (!class_exists('PHPMailer')) {
    $opd_pm = __DIR__ . '/smtpmail/phpmailer/class.phpmailer.php';
    if (file_exists($opd_pm)) {
        include ($opd_pm);
    }
}

$opd_dry_run = (PHP_SAPI === 'cli' && isset($argv) && in_array('--dry-run', $argv, true));

/* customers that get their own section (shown first, in this order) and customers excluded everywhere */
define('OPD_PRIORITY_CUSTOMERS', '66, 75, 49');
define('OPD_EXCLUDED_CUSTOMERS', '66, 75, 49, 44');
define('OPD_CONVERSION_FROM', '2025-09-01');
define('OPD_ONE_TIME_FROM', '2025-06-01');

/* ---------------------------- helpers ---------------------------- */

/* All rows of a query as an array. A failing query is logged and gives an empty array instead of a fatal error. */
function opd_rows($sql)
{
    global $db;
    $out = array();
    $res = $db->query($sql);
    if ($res === false) {
        error_log('op_due_articles SQL error: ' . $db->error . ' | ' . $sql);
        return $out;
    }
    while ($r = $res->fetch_assoc()) {
        $out[] = $r;
    }
    return $out;
}

function opd_h($v)
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/* d-m-Y, blank for empty / zero dates (instead of 01-01-1970). */
function opd_date($v)
{
    if ($v === null || $v === '' || strpos((string) $v, '0000-00-00') === 0) {
        return '';
    }
    $ts = strtotime($v);
    return $ts ? date('d-m-Y', $ts) : '';
}

/* Row colour from the due date: red = overdue, yellow = due today. Inline, because e-mail clients drop <style> classes. */
function opd_row_style($due)
{
    $ts = ($due === null || $due === '') ? false : strtotime($due);
    if (!$ts) {
        return '';
    }
    $today = date('Y-m-d');
    $d = date('Y-m-d', $ts);
    if ($today > $d) {
        return ' class="ct" style="background-color:#f51f1f;"';
    }
    if ($today == $d) {
        return ' class="eq" style="background-color:#f5f538;"';
    }
    return '';
}

/* One-time projects table. $cust_filter is an SQL fragment ("AND c.id = 5"). Returns '' when there are no rows. */
function opd_one_time_table($cust_filter)
{
    $query = "SELECT c.cust_name,journal_title,recv_dt,due_dt,status,wd.id,stage,r.received_date,r.due_date,wd.article_title,wd.cust_id,r.type as rstage
        FROM `one_time_project_dtl` as wd
        LEFT JOIN one_time_project_revisions_dtl as r ON (wd.`id` = r.`inw_id` AND r.r_id = (select r_id from one_time_project_revisions_dtl where inw_id = wd.`id` order by r_id DESC limit 1)),
        adm_customer_master as c, one_time_projects_config as j
        WHERE wd.cust_id = c.id AND wd.j_id = j.j_id
        AND wd.status NOT IN ('Delivery','Client Review','Completed','Query','Hold')
        AND wd.v_issue_id <= 0 AND wd.created_dt > '" . OPD_ONE_TIME_FROM . "' " . $cust_filter . "
        ORDER BY wd.cust_id, wd.due_dt";
    $rows = opd_rows($query);
    if (!$rows) {
        return '';
    }

    $html = '<table id="customers">
        <col width="5%"><col width="5%"><col width="30%"><col width="10%"><col width="20%">
        <col width="15%"><col width="10%"><col width="5%"><col width="5%"><col width="5%">
        <tr>
            <th>S.No</th><th>Customer S.NO</th><th>Customer</th><th>Type</th><th>Project Name</th>
            <th>Received Date</th><th>Due Date</th><th>Stage</th><th>Department</th><th>Query</th>
        </tr>';
    $filecount = 1;
    $customer_sn = 0;
    $customer_id = null;
    foreach ($rows as $a) {
        // serial number inside each customer
        if ($customer_id !== $a['cust_id']) {
            $customer_id = $a['cust_id'];
            $customer_sn = 0;
        }
        $customer_sn++;

        // FP uses the project dates, later stages use the latest revision (falls back to the project dates)
        $recv = $a['recv_dt'];
        $due = $a['due_dt'];
        if ($a['stage'] != 'FP' && !empty($a['due_date'])) {
            $due = $a['due_date'];
            $recv = !empty($a['received_date']) ? $a['received_date'] : $a['recv_dt'];
        }

        $html .= '<tr' . opd_row_style($due) . '>
            <td>' . $filecount . '</td>
            <td>' . $customer_sn . '</td>
            <td>' . opd_h($a['cust_name']) . '</td>
            <td>' . opd_h($a['journal_title']) . '</td>
            <td>' . opd_h($a['article_title']) . '</td>
            <td>' . opd_date($recv) . '</td>
            <td>' . opd_date($due) . '</td>
            <td>' . opd_h($a['stage']) . '</td>
            <td>' . opd_h($a['status']) . '</td>
            <td>&nbsp;</td>
        </tr>';
        $filecount++;
    }
    return $html . '</table>';
}

/* Department name by code, cached (was one query per chapter row). */
function opd_dept_name($code)
{
    static $cache = array();
    if (!isset($cache[$code])) {
        global $db;
        $r = opd_rows("SELECT dept_name FROM adm_dept_master WHERE dept_code = '" . $db->real_escape_string((string) $code) . "' LIMIT 1");
        $cache[$code] = $r ? $r[0]['dept_name'] : '';
    }
    return $cache[$code];
}

/* Conversion books table for one customer, one row per chapter. Returns '' when there are no rows. */
function opd_conversion_table($cust_id)
{
    $cust_id = (int) $cust_id;
    $books = opd_rows("SELECT * FROM inw_conversion_dtl as inw WHERE inw.status NOT IN ('Delivery','Client Review','Completed','Query','Hold') AND inw.created_dt > '" . OPD_CONVERSION_FROM . "' AND inw.stage !='' AND inw.cust_id = " . $cust_id . " ORDER BY inw.cust_id, inw.due_dt ASC");
    if (!$books) {
        return '';
    }

    $html = '<table id="customers">
        <tr>
            <th>S.No</th><th>Project Name</th><th>Work Type</th><th>Stage</th><th>Department</th>
            <th>Page Count</th><th>Fig Count</th><th>Table Count</th><th>Received Date</th><th>Due Date</th><th>Query</th>
        </tr>';
    $filecount = 1;
    foreach ($books as $book) {
        $book_id = (int) $book['id'];

        // latest revision overrides the book dates
        $due_date = $book['due_dt'];
        $received_date = $book['recv_dt'];
        $rev = opd_rows("SELECT due_date,received_date FROM `inw_conversion_revisions_dtl` WHERE b_id = '" . $book_id . "' ORDER BY r_id DESC LIMIT 1");
        if ($rev) {
            $due_date = $rev[0]['due_date'];
            $received_date = $rev[0]['received_date'];
        }
        $style = opd_row_style($due_date);

        $chapters = opd_rows("SELECT * FROM inw_conversion_project_dtl as ch WHERE ch.created_dt > '" . OPD_CONVERSION_FROM . "' AND ch.stage != '' AND ch.b_id='" . $book_id . "'");
        $rowspan = max(1, count($chapters));

        $first = true;
        foreach ($chapters as $ch) {
            $html .= '<tr' . $style . '>';
            if ($first) {
                $html .= '<td rowspan="' . $rowspan . '">' . $filecount . '</td>'
                    . '<td rowspan="' . $rowspan . '">' . opd_h($book['book_short_name']) . '</td>';
                $first = false;
            }
            $html .= '<td>' . opd_h($book['digital_type']) . '</td>
                <td>' . opd_h($ch['stage']) . '</td>
                <td>' . opd_h(opd_dept_name($ch['department_id'])) . '</td>
                <td>' . opd_h($ch['manuscript_count']) . '</td>
                <td>' . opd_h($ch['fig']) . '</td>
                <td>' . opd_h($ch['tab']) . '</td>
                <td>' . opd_date($received_date) . '</td>
                <td>' . opd_date($due_date) . '</td>
                <td>&nbsp;</td>
            </tr>';
        }
        if ($first) {
            // book without chapter rows: still list it, with its own stage and dates
            $html .= '<tr' . $style . '>
                <td>' . $filecount . '</td>
                <td>' . opd_h($book['book_short_name']) . '</td>
                <td>' . opd_h($book['digital_type']) . '</td>
                <td>' . opd_h($book['stage']) . '</td>
                <td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td>
                <td>' . opd_date($received_date) . '</td>
                <td>' . opd_date($due_date) . '</td>
                <td>&nbsp;</td>
            </tr>';
        }
        $filecount++;
    }
    return $html . '</table>';
}

function opd_heading($text)
{
    return '<br/><br/><div id="t_div">' . opd_h($text) . '</div>';
}

/* ------------------------- mail settings ------------------------- */
$schedule = opd_rows("SELECT * FROM `adm_schedule_mail_details` WHERE type = 'WIP_Schedule_mail_details' LIMIT 1");
if (!$schedule && !$opd_dry_run) {
    error_log('op_due_articles: no WIP_Schedule_mail_details row found, nothing sent');
    echo "Schedule mail settings not found.\n";
    exit;
}
$schedule_details = $schedule ? $schedule[0] : array('from_email' => '', 'from_email_pwd' => '', 'to_email' => '', 'cc_email' => '', 'bcc_email' => '');

$mail_from = $schedule_details['from_email'];
$mail_pass = $schedule_details['from_email_pwd'];
$to_mails = $schedule_details['to_email'];
$cc_mails = $schedule_details['cc_email'];
$bcc_mails = $schedule_details['bcc_email'];

/* ------------------------- mail content ------------------------- */
$head = '<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
table{
	width:80%;
}
#customers {
  font-family: "Trebuchet MS", Arial, Helvetica, sans-serif;
  border-collapse: collapse;
  width: 100%;
}

#customers td, #customers th {
  border: 1px solid #ddd;
  padding: 8px;
}

#customers th {
  padding-top: 12px;
  padding-bottom: 12px;
  text-align: left;
  background-color: #4CAF50;
  color: white;
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
.ct{
background-color:#f51f1f !important;
}
.eq{
background-color:#f5f538 !important;
}
</style>
</head>
<body>
';
$close = "</body>
</html>";

$body = "";

/* 1. one-time projects of all customers that do not have their own section */
$t = opd_one_time_table(" AND c.id NOT IN (" . OPD_EXCLUDED_CUSTOMERS . ")");
if ($t !== '') {
    $body .= opd_heading('One Time Project Details') . $t;
}

/* 2. priority customers: one-time projects + conversion books */
$priority = opd_rows("SELECT adc.id, adc.cust_name FROM inw_conversion_dtl AS inw JOIN adm_customer_master AS adc ON inw.cust_id = adc.id
    WHERE inw.status != 'Client Review' AND inw.created_dt > '" . OPD_CONVERSION_FROM . "' AND inw.cust_id IN (" . OPD_PRIORITY_CUSTOMERS . ")
    GROUP BY adc.id, adc.cust_name ORDER BY adc.id ASC");
foreach ($priority as $cust) {
    if ($cust['id'] <= 0) {
        continue;
    }
    $section = '';
    $ot = opd_one_time_table(" AND c.id = " . (int) $cust['id']);
    if ($ot !== '') {
        $section .= opd_heading('One Time Project Details') . $ot;
    }
    $cv = opd_conversion_table($cust['id']);
    if ($cv !== '') {
        if (trim($cust['cust_name']) != 'Springer_FoulCheck') {
            $section .= opd_heading('Conversion Project Details');
        }
        $section .= $cv;
    }
    if ($section !== '') {
        $body .= opd_heading($cust['cust_name']) . $section;
    }
}

/* 3. all other customers: conversion books */
$others = opd_rows("SELECT adc.id, adc.cust_name FROM inw_conversion_dtl AS inw JOIN adm_customer_master AS adc ON inw.cust_id = adc.id
    WHERE inw.status != 'Client Review' AND inw.created_dt > '" . OPD_CONVERSION_FROM . "' AND inw.cust_id NOT IN (" . OPD_EXCLUDED_CUSTOMERS . ")
    GROUP BY adc.id, adc.cust_name ORDER BY adc.cust_name ASC");
foreach ($others as $cust) {
    if ($cust['id'] <= 0) {
        continue;
    }
    $cv = opd_conversion_table($cust['id']);
    if ($cv !== '') {
        $body .= opd_heading($cust['cust_name']) . $cv;
    }
}

/* ------------------------- send ------------------------- */
if ($body != "") {
    $month_date = date('md');
    $mail_subject = "Conversion - Schedule " . $month_date;
    // greeting / sign-off are part of the one HTML document (it used to be a whole document nested inside another)
    $html = $head . "Dear Team, <br/><br/>Kindly follow the below mentioned schedule.<br/><br/>" . $body . "<br/><br/>Regards,<br><br>
        Transforma .<br><br>
        *** This is an automated system generated e-mail, please do not return your correction to this mail." . $close;

    if ($opd_dry_run) {
        echo $html;
    } elseif (!class_exists('PHPMailer')) {
        error_log('op_due_articles: PHPMailer class not available, nothing sent');
        echo "Mailer Error: PHPMailer class not found.\n";
    } else {
        $mail = new PHPMailer();
        $mail->IsSMTP();
        $mail->CharSet = 'UTF-8';
        $mail->Host = "outlook.office365.com";
        $mail->SMTPAuth = true;
        $mail->Port = 587;
        $mail->Username = $mail_from;
        $mail->Password = $mail_pass;
        $mail->SMTPSecure = 'tls';
        $mail->From = $mail_from;
        $mail->FromName = $mail_from;
        $mail->isHTML(true);
        $mail->clearAllRecipients();
        $mail->Subject = $mail_subject;
        $mail->Body = $html;
        $mail->AltBody = 'Please view this e-mail in an HTML-capable mail client to see the conversion schedule.';

        $recipients = 0;
        foreach (explode(',', (string) $to_mails) as $email) {
            $email = trim($email);
            if ($email != '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->addAddress($email);
                $recipients++;
            }
        }
        foreach (explode(',', (string) $cc_mails) as $email) {
            $email = trim($email);
            if ($email != '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->AddCC($email);
            }
        }
        foreach (explode(',', (string) $bcc_mails) as $email) {
            $email = trim($email);
            if ($email != '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $mail->AddBCC($email);
            }
        }

        if ($recipients == 0) {
            error_log('op_due_articles: no valid To address in adm_schedule_mail_details, nothing sent');
            echo "Mailer Error: no valid recipient.\n";
        } elseif (!$mail->Send()) {
            error_log('op_due_articles mail error: ' . $mail->ErrorInfo);
            echo "Mailer Error: " . $mail->ErrorInfo;
            // schedule mail history insert intentionally left out (was commented out in the original)
        } else {
            echo "Message sent!";
        }
    }
}

if ($opd_own_db) {
    $db->close();
}
