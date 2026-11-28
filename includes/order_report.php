<?php

function reportDate(mixed $value, string $fallback): string
{
    if (!is_string($value) || !preg_match('/^[1-9]\d{3}-\d{2}-\d{2}$/D', trim($value))) {
        return $fallback;
    }

    $value = trim($value);
    [$year, $month, $day] = array_map('intval', explode('-', $value));
    return checkdate($month, $day, $year) ? $value : $fallback;
}

function orderReportFilters(array $query): array
{
    $today = date('Y-m-d');
    $fromDate = reportDate($query['from_date'] ?? null, $today);
    $toDate = reportDate($query['to_date'] ?? null, $today);
    if ($fromDate > $toDate) {
        [$fromDate, $toDate] = [$toDate, $fromDate];
    }

    $shiftId = filter_var($query['shift_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
    $payment = $query['payment_method'] ?? '';
    $paymentMethod = is_string($payment) ? strtoupper(trim($payment)) : '';
    if (!in_array($paymentMethod, ['CASH', 'CARD', 'ONLINE'], true)) {
        $paymentMethod = '';
    }

    $where = ['o.created_at >= ?', 'o.created_at <= ?'];
    $params = [$fromDate . ' 00:00:00', $toDate . ' 23:59:59'];
    if ($shiftId > 0) {
        $where[] = 'o.shift_id = ?';
        $params[] = $shiftId;
    }
    if ($paymentMethod !== '') {
        $where[] = 'o.payment_method = ?';
        $params[] = $paymentMethod;
    }

    $whereSql = implode(' AND ', $where);
    return compact('fromDate', 'toDate', 'shiftId', 'paymentMethod', 'whereSql', 'params');
}
