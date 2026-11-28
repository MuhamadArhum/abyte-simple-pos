<?php
// CSV export of filtered orders (matches reports.php filters).
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';

require_once __DIR__ . '/includes/order_report.php';

['whereSql' => $whereSql, 'params' => $params] = orderReportFilters($_GET);

$sql = "SELECT o.id, o.order_no, o.created_at, o.shift_id, s.cashier_name, o.payment_method, o.total_amount
        FROM orders o
        LEFT JOIN shifts s ON s.id = o.shift_id
        WHERE {$whereSql}
        ORDER BY o.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Also grab order items so each order can be summarized.
$itemsByOrder = [];
$ids = array_column($orders, 'id');
if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $iStmt = $pdo->prepare(
        "SELECT order_id, item_name, qty, unit_price, line_total
         FROM order_items WHERE order_id IN ($placeholders) ORDER BY id ASC"
    );
    $iStmt->execute(array_map('intval', $ids));
    foreach ($iStmt->fetchAll() as $row) {
        $itemsByOrder[(int) $row['order_id']][] = $row;
    }
}

$filename = 'orders-' . date('Ymd-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
// UTF-8 BOM for Excel
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, ['Order #', 'Date & Time', 'Shift', 'Cashier', 'Payment', 'Total', 'Items']);

foreach ($orders as $o) {
    $oid = (int) $o['id'];
    $itemsSummary = '';
    if (!empty($itemsByOrder[$oid])) {
        $parts = [];
        foreach ($itemsByOrder[$oid] as $it) {
            $parts[] = $it['item_name'] . ' x' . (int) $it['qty'];
        }
        $itemsSummary = implode('; ', $parts);
    }
    fputcsv($out, [
        $o['order_no'],
        $o['created_at'],
        $o['shift_id'] ? '#' . (int) $o['shift_id'] : '',
        (string) ($o['cashier_name'] ?? ''),
        $o['payment_method'],
        number_format((float) $o['total_amount'], 2, '.', ''),
        $itemsSummary,
    ]);
}

fclose($out);
exit;
