<?php
/*
 * PayerNex Quick Pay — has DHRU recorded this deposit yet?
 * ?d=<PayerNex deposit id>. Read-only; answers only credited / not yet.
 */
require_once __DIR__ . '/lib.php';

if (!pnx_rate_limit('status', 150, 60)) pnx_out(429, array('credited' => false));
$id = isset($_GET['d']) ? (int) $_GET['d'] : 0;
if ($id <= 0) pnx_out(400, array('credited' => false));

$db = pnx_db();
if (!$db) pnx_out(500, array('credited' => false));
$transid = 'PNXEW-' . $id;
$stmt = $db->prepare('SELECT amountin FROM tbl_transaction WHERE transid = ? LIMIT 1');
$stmt->bind_param('s', $transid);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();
pnx_out(200, $row ? array('credited' => true, 'amount' => (float) $row['amountin']) : array('credited' => false));
