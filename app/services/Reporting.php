<?php
/**
 * Reporting helpers: FIFO allocation of receipts/payments to open invoices for aging.
 */
final class Reporting {

    /**
     * Aging for one party. Returns ['buckets' => [...], 'invoices' => [...], 'total_outstanding' => float]
     * $partyType: 'customer' (credit invoices vs receipts) | 'supplier' (credit purchase invoices vs payments)
     */
    public static function aging(string $partyType, ?int $partyId = null): array {
        $isCustomer = $partyType === 'customer';
        $invoiceTable = $isCustomer ? 'sales_invoices' : 'purchase_invoices';
        $itemName = $isCustomer ? 'customer_id' : 'supplier_id';
        $paymentTypeFilter = "AND payment_type = 'credit'";
        $docFilter = $partyId ? "AND $itemName = " . (int)$partyId . " " : '';

        $dueCol = $isCustomer ? 'due_date' : 'NULL';
        $invoices = db_all("SELECT id, $itemName AS party_id, invoice_no, date, $dueCol AS due_date, total FROM $invoiceTable
            WHERE status = 'posted' $paymentTypeFilter $docFilter ORDER BY date, id");

        if ($isCustomer) {
            $payments = db_all("SELECT id, customer_id AS party_id, receipt_no AS no, date, amount FROM receipts ORDER BY date, id");
        } else {
            $payments = db_all("SELECT id, party_id AS party_id, payment_no AS no, date, amount FROM payments WHERE party_type = 'supplier' ORDER BY date, id");
        }

        // Group payment pools per party, FIFO match to invoices
        $pools = [];
        foreach ($payments as $pay) $pools[(int)$pay['party_id']][] = ['amount' => (float)$pay['amount'], 'date' => $pay['date']];

        $buckets = ['current' => 0.0, 'd1_30' => 0.0, 'd31_60' => 0.0, 'd61_90' => 0.0, 'd90_plus' => 0.0];
        $open = [];
        $today = new DateTimeImmutable('today');

        foreach ($invoices as $inv) {
            $remaining = (float)$inv['total'];
            $pool = &$pools[(int)$inv['party_id']];
            if ($pool) {
                foreach ($pool as &$p) {
                    if ($remaining <= 0) break;
                    $take = min($p['amount'], $remaining);
                    $p['amount'] -= $take;
                    $remaining -= $take;
                }
                // drop zeroed pool entries
                $pool = array_values(array_filter($pool, fn($p) => $p['amount'] > 0.004));
                unset($p);
            }
            if ($remaining <= 0.004) continue;
            $refDate = $inv['due_date'] ? new DateTimeImmutable($inv['due_date']) : (new DateTimeImmutable($inv['date']))->modify('+30 days');
            $daysOverdue = (int)$today->diff($refDate)->days * ($today >= $refDate ? 1 : -1);
            if ($daysOverdue <= 0) $buckets['current'] += $remaining;
            elseif ($daysOverdue <= 30) $buckets['d1_30'] += $remaining;
            elseif ($daysOverdue <= 60) $buckets['d31_60'] += $remaining;
            elseif ($daysOverdue <= 90) $buckets['d61_90'] += $remaining;
            else $buckets['d90_plus'] += $remaining;
            $open[] = ['doc' => $inv['invoice_no'], 'date' => $inv['date'], 'due_date' => $inv['due_date'], 'party_id' => (int)$inv['party_id'], 'outstanding' => round($remaining, 2), 'days_overdue' => max(0, $daysOverdue)];
        }

        return ['buckets' => array_map(fn($v) => round($v, 2), $buckets), 'invoices' => $open,
                'total_outstanding' => round(array_sum(array_column($open, 'outstanding')), 2)];
    }
}
