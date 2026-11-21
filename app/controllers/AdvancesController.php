<?php
/** Combined Advances section — every registered person (salesmen + employees). Reuses existing advance tables. */
final class AdvancesController {

    public static function index(): void {
        $q = trim(inp('q'));
        $kind = in_array(inp('kind'), ['all', 'salesman', 'labour', 'employee'], true) ? inp('kind') : 'all';
        $designation = trim(inp('designation'));

        $people = PeopleDirectory::allActive();
        $rows = [];
        $totalGiven = 0.0; $totalRecovered = 0.0; $totalOutstanding = 0.0; $totalRequested = 0.0; $totalApproved = 0.0;

        foreach ($people as $p) {
            if ($kind !== 'all' && $p['kind'] !== $kind) continue;
            if ($designation !== '' && strcasecmp($p['designation'], $designation) !== 0) continue;
            if ($q !== '') {
                $hay = strtolower($p['name'] . ' ' . $p['code'] . ' ' . $p['phone'] . ' ' . $p['designation']);
                if (strpos($hay, strtolower($q)) === false) continue;
            }
            $stats = self::advanceStats($p);
            $rows[] = array_merge($p, $stats);
            $totalGiven += $stats['total_given'];
            $totalRecovered += $stats['total_recovered'];
            $totalOutstanding += $stats['outstanding'];
            $totalRequested += $stats['total_requested'];
            $totalApproved += $stats['total_approved'];
        }

        // designations for dropdown
        $designations = [];
        foreach ($people as $p) { if ($kind === 'all' || $p['kind'] === $kind) $designations[$p['designation']] = true; }
        ksort($designations);
        $designations = array_keys($designations);

        view('advances/index', [
            'rows' => $rows,
            'q' => $q, 'kind' => $kind, 'designation' => $designation,
            'designations' => $designations,
            'totalGiven' => $totalGiven, 'totalRecovered' => $totalRecovered, 'totalOutstanding' => $totalOutstanding,
            'totalRequested' => $totalRequested, 'totalApproved' => $totalApproved,
            'totalPeople' => count($rows),
            'title' => __('Advances'),
        ]);
    }

    /** Advance payment voucher for one employee (printable). Reuses existing employee_advances. */
    public static function employeeVoucher(): void {
        $id = intv(inp('id'));
        $p = PeopleDirectory::find('employee', $id);
        if (!$p) { http_response_code(404); exit('Employee not found'); }
        $stmt = db_one('SELECT id FROM employee_advances WHERE employee_id = ? ORDER BY date DESC, id DESC LIMIT 1', [$id]);
        $advId = $stmt ? (int)$stmt['id'] : 0;
        if (!$advId) {
            view('advances/voucher_none', ['p' => $p, 'personType' => 'employee', 'title' => 'Advance Payment Voucher — ' . $p['name']]);
            return;
        }
        $adv = db_one('SELECT * FROM employee_advances WHERE id = ?', [$advId]);
        $stats = self::advanceStats($p);
        $outstanding = (float)$stats['outstanding'];
        view('advances/voucher_employee', ['p' => $p, 'adv' => $adv, 'stats' => $stats, 'title' => 'Advance Payment Voucher — ' . $p['name']]);
    }

    /** Advance payment voucher for one salesman (printable). Reuses existing salesman_salary_advances. */
    public static function salesmanVoucher(): void {
        $id = intv(inp('id'));
        $p = PeopleDirectory::find('salesman', $id);
        if (!$p) { http_response_code(404); exit('Salesman not found'); }
        $aid = intv(inp('aid'));
        if ($aid > 0) {
            $stmt = db_one('SELECT id FROM salesman_salary_advances WHERE id = ? AND salesman_id = ?', [$aid, $id]);
        } else {
            $stmt = db_one('SELECT id FROM salesman_salary_advances WHERE salesman_id = ? ORDER BY date DESC, id DESC LIMIT 1', [$id]);
        }
        $advId = $stmt ? (int)$stmt['id'] : 0;
        if (!$advId) {
            view('advances/voucher_none', ['p' => $p, 'personType' => 'salesman', 'title' => 'Advance Payment Voucher — ' . $p['name']]);
            return;
        }
        $adv = db_one('SELECT * FROM salesman_salary_advances WHERE id = ?', [$advId]);
        $stats = self::advanceStats($p);
        view('advances/voucher_salesman', ['p' => $p, 'adv' => $adv, 'stats' => $stats, 'title' => 'Advance Payment Voucher — ' . $p['name']]);
    }

    /** Total/recovered/outstanding figures for advances of one person (requested → approved → paid → recovered). */
    public static function advanceStats(array $p): array {
        if ($p['person_type'] === 'employee') {
            $row = db_one("SELECT COALESCE(SUM(amount),0) AS requested,
                                     COALESCE(SUM(CASE WHEN status IN ('approved','disbursed') THEN amount ELSE 0 END),0) AS approved,
                                     COALESCE(SUM(CASE WHEN status = 'disbursed' THEN amount ELSE 0 END),0) AS paid,
                                     COALESCE(SUM(CASE WHEN status = 'disbursed' THEN deducted ELSE 0 END),0) AS recovered
                              FROM employee_advances WHERE employee_id = ?", [(int)$p['id']]);
        } else {
            $row = db_one("SELECT COALESCE(SUM(amount),0) AS requested,
                                     COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END),0) AS approved,
                                     COALESCE(SUM(CASE WHEN status = 'paid' THEN amount ELSE 0 END),0) AS paid,
                                     COALESCE(SUM(CASE WHEN status = 'paid' THEN deducted ELSE 0 END),0) AS recovered
                              FROM salesman_salary_advances WHERE salesman_id = ?", [(int)$p['id']]);
        }
        $requested = (float)($row['requested'] ?? 0);
        $approved = (float)($row['approved'] ?? 0);
        $paid = (float)($row['paid'] ?? 0);
        $recovered = (float)($row['recovered'] ?? 0);
        return [
            'total_requested' => round($requested, 2),
            'total_approved' => round($approved, 2),
            'total_given' => round($paid, 2),
            'total_recovered' => round($recovered, 2),
            'outstanding' => max(0, round($paid - $recovered, 2)),
        ];
    }
}
