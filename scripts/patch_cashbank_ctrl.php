<?php
$ctrlFile = __DIR__ . '/../app/controllers/CashBankController.php';
$content = file_get_contents($ctrlFile);

$methodCode = <<<'PHP'

    /** Daily Cash & Bank Position: Opening + Today In - Today Out = Closing */
    public static function dailyPosition(): void {
        $date = inp('date');
        $date = (is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) ? $date : today();

        // 1. All active cash & bank accounts
        $accounts = db_all("SELECT a.*, coa.code AS gl_code, coa.name AS gl_name
                            FROM cash_bank_accounts a
                            LEFT JOIN chart_of_accounts coa ON coa.id = a.gl_account_id
                            WHERE a.is_active = 1
                            ORDER BY a.type, a.id");

        $totalOpening = 0.0;
        $totalIn = 0.0;
        $totalOut = 0.0;
        $totalClosing = 0.0;

        foreach ($accounts as &$acc) {
            $glId = (int)$acc['gl_account_id'];
            $openingBal = (float)$acc['opening_balance'];
            $priorNet = 0.0;
            $dayIn = 0.0;
            $dayOut = 0.0;

            if ($glId > 0) {
                // Movement prior to selected date
                $priorNet = (float)db_val("SELECT COALESCE(SUM(l.debit - l.credit),0)
                    FROM journal_lines l
                    JOIN journal_entries e ON e.id = l.entry_id
                    WHERE l.account_id = ? AND e.date < ? AND e.status = 'posted'", [$glId, $date]);

                // Movement on selected date
                $dayIn = (float)db_val("SELECT COALESCE(SUM(l.debit),0)
                    FROM journal_lines l
                    JOIN journal_entries e ON e.id = l.entry_id
                    WHERE l.account_id = ? AND e.date = ? AND e.status = 'posted'", [$glId, $date]);

                $dayOut = (float)db_val("SELECT COALESCE(SUM(l.credit),0)
                    FROM journal_lines l
                    JOIN journal_entries e ON e.id = l.entry_id
                    WHERE l.account_id = ? AND e.date = ? AND e.status = 'posted'", [$glId, $date]);
            }

            $acc['day_opening'] = round($openingBal + $priorNet, 2);
            $acc['day_in']      = round($dayIn, 2);
            $acc['day_out']     = round($dayOut, 2);
            $acc['day_closing'] = round($acc['day_opening'] + $acc['day_in'] - $acc['day_out'], 2);

            $totalOpening += $acc['day_opening'];
            $totalIn      += $acc['day_in'];
            $totalOut     += $acc['day_out'];
            $totalClosing += $acc['day_closing'];
        }
        unset($acc);

        // 2. Transactions on selected date touching these accounts
        $glIds = array_filter(array_column($accounts, 'gl_account_id'));
        $transactions = [];
        if (!empty($glIds)) {
            $inClause = implode(',', array_map('intval', $glIds));
            $transactions = db_all("SELECT e.id AS entry_id, e.entry_no, e.date, e.voucher_type, e.reference_type, e.reference_id,
                                           e.memo AS entry_memo, l.debit, l.credit, l.memo AS line_memo,
                                           cba.name AS account_name, cba.type AS account_type
                                    FROM journal_lines l
                                    JOIN journal_entries e ON e.id = l.entry_id
                                    JOIN cash_bank_accounts cba ON cba.gl_account_id = l.account_id
                                    WHERE l.account_id IN ($inClause) AND e.date = ? AND e.status = 'posted'
                                    ORDER BY e.id DESC, l.id DESC", [$date]);
        }

        view('cashbank/daily_position', [
            'date' => $date,
            'accounts' => $accounts,
            'totalOpening' => $totalOpening,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'totalClosing' => $totalClosing,
            'netMovement' => round($totalIn - $totalOut, 2),
            'transactions' => $transactions,
            'title' => __('Daily Cash Position (آج کا کیش پوزیشن)')
        ]);
    }
}
PHP;

// Replace last closing brace with method + closing brace
if (strpos($content, 'function dailyPosition') === false) {
    $lastBrace = strrpos($content, '}');
    $newContent = substr($content, 0, $lastBrace) . $methodCode;
    file_put_contents($ctrlFile, $newContent);
    echo "Added dailyPosition to CashBankController\n";
} else {
    echo "dailyPosition already exists\n";
}
