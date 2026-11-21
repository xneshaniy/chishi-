<?php
/**
 * Unified Salary section — shows employees, labour workers AND salesmen in one list.
 * Reuses existing profiles/salaries/advances/bonuses/deductions — no duplicate records or ledgers.
 */
final class SalaryController {

    /* ============ Combined salary list ============ */

    public static function index(): void {
        $q = trim(inp('q'));
        $kind = in_array(inp('kind'), ['all', 'salesman', 'labour', 'employee'], true) ? inp('kind') : 'all';
        $designation = trim(inp('designation'));
        $status = in_array(inp('status'), ['all', 'settled', 'partial', 'unpaid', 'no_salary'], true) ? inp('status') : 'all';
        $month = preg_match('/^\d{4}-\d{2}$/', inp('month', '')) ? inp('month') : null;

        $people = PeopleDirectory::allActive();
        $rows = [];
        $totalPaid = 0.0; $totalRemaining = 0.0; $totalEarned = 0.0; $advancesOut = 0.0;

        foreach ($people as $p) {
            // filter: kind
            if ($kind !== 'all' && $p['kind'] !== $kind) continue;
            // filter: designation
            if ($designation !== '' && strcasecmp($p['designation'], $designation) !== 0) continue;
            // search
            if ($q !== '') {
                $hay = strtolower($p['name'] . ' ' . $p['code'] . ' ' . $p['phone'] . ' ' . $p['designation']);
                if (strpos($hay, strtolower($q)) === false) continue;
            }
            $stats = PeopleDirectory::salaryStats($p, $month);
            // filter: status
            if (!self::statusMatches($stats, $status)) continue;
            $row = array_merge($p, $stats);
            $row['status'] = self::personStatus($stats);
            $rows[] = $row;
            $totalPaid += $stats['paid'];
            $totalRemaining += max(0, $stats['remaining']);
            $totalEarned += $stats['earned'];
            $advancesOut += $stats['advances_out'];
        }

        // distinct designations present in the (already kind-filtered) people for the dropdown
        $designations = [];
        foreach ($people as $p) { if ($kind === 'all' || $p['kind'] === $kind) $designations[$p['designation']] = true; }
        ksort($designations);
        $designations = array_keys($designations);

        view('salary/index', [
            'rows' => $rows, 'q' => $q, 'kind' => $kind, 'designation' => $designation, 'status' => $status,
            'month' => $month, 'designations' => $designations,
            'totalPeople' => count($rows), 'totalEarned' => $totalEarned, 'totalPaid' => $totalPaid,
            'totalRemaining' => $totalRemaining, 'advancesOut' => $advancesOut,
            'title' => __('Salary'),
        ]);
    }

    /** Derived payment status for filter chips. */
    private static function personStatus(array $s): string {
        if ($s['rate'] <= 0 && $s['earned'] <= 0) return 'no_salary';
        if ($s['remaining'] <= 0.009 && $s['paid'] > 0) return 'settled';
        if ($s['paid'] > 0.004) return 'partial';
        return 'unpaid';
    }

    private static function statusMatches(array $stats, string $status): bool {
        if ($status === 'all') return true;
        return self::personStatus($stats) === $status;
    }

    /* ============ Individual salary details ============ */

    public static function show(): void {
        $type = inp('type'); $id = intv(inp('id'));
        $p = ($type === 'employee' || $type === 'salesman') ? PeopleDirectory::find($type, $id) : null;
        if (!$p) { http_response_code(404); view('error/404', ['title' => 'Page not found']); return; }
        $month = preg_match('/^\d{4}-\d{2}$/', inp('month', '')) ? inp('month') : null;
        $stats = PeopleDirectory::salaryStats($p, $month);
        $stats['status'] = self::personStatus($stats);
        $attendanceTotals = StaffAttendance::personTotals((int)$p['id'], $p['person_type']);

        if ($p['person_type'] === 'employee') {
            $payments = db_all("SELECT pp.id, pp.date, pp.amount, pp.status, pp.account_id, ca.name AS account, p.period_start, p.period_end, p.net_pay, p.amount_paid
                FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id LEFT JOIN cash_bank_accounts ca ON ca.id = pp.account_id
                WHERE p.employee_id = ? ORDER BY pp.date DESC, pp.id DESC", [(int)$p['id']]);
            $advances = db_all("SELECT * FROM employee_advances WHERE employee_id = ? ORDER BY date DESC, id DESC", [(int)$p['id']]);
            $deductions = db_all("SELECT * FROM employee_deductions WHERE employee_id = ? ORDER BY date DESC, id DESC", [(int)$p['id']]);
            $bonusRows = db_all("SELECT * FROM employee_bonuses WHERE employee_id = ? ORDER BY date DESC, id DESC", [(int)$p['id']]);
            $latestPayslip = db_one("SELECT * FROM payroll WHERE employee_id = ? AND status <> 'reversed' ORDER BY period_start DESC LIMIT 1", [(int)$p['id']]);
        } else {
            $payments = db_all("SELECT sp.id, sp.date, sp.amount, sp.status, sp.account_id, ca.name AS account, sp.period, NULL AS period_start, NULL AS period_end
                FROM salesman_salary_payments sp LEFT JOIN cash_bank_accounts ca ON ca.id = sp.account_id WHERE sp.salesman_id = ? ORDER BY sp.date DESC, sp.id DESC", [(int)$p['id']]);
            $advances = db_all("SELECT * FROM salesman_salary_advances WHERE salesman_id = ? ORDER BY date DESC, id DESC", [(int)$p['id']]);
            $deductions = [];
            $bonusRows = [];
            $latestPayslip = null;
        }

        view('salary/show', [
            'p' => $p, 'stats' => $stats, 'month' => $month, 'attendance' => $attendanceTotals,
            'payments' => $payments, 'advances' => $advances, 'deductions' => $deductions, 'bonusRows' => $bonusRows,
            'latestPayslip' => $latestPayslip ?? null,
            'title' => 'Salary — ' . $p['name'],
        ]);
    }
}
