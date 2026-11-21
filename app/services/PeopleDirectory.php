<?php
/**
 * Combined directory of everyone registered in the ERP: employees (employees table, incl. labour via `kind`) and salesmen (salesmen table).
 * Pure read-model — never creates records; "people" maps 1:1 to existing profiles/salaries/attendance.
 */
final class PeopleDirectory {

    /** Months from join_date to first day of month (inclusive of current month). */
    public static function monthsSinceJoin(?string $joinDate, ?string $asOfYm = null): int {
        if (!$joinDate) return 0;
        $join = new DateTimeImmutable($joinDate);
        $ref = $asOfYm ? new DateTimeImmutable($asOfYm . '-01') : new DateTimeImmutable('first day of this month');
        return max(0, ((int)$ref->format('Y') - (int)$join->format('Y')) * 12 + ((int)$ref->format('n') - (int)$join->format('n')) + 1);
    }

    /** Salary stats for one person (employees or salesmen). */
    public static function salaryStats(array $p, ?string $month = null): array {
        $rate = (float)($p['salary_rate'] ?? 0);
        $type = $p['salary_frequency'] ?? ($p['salary_type'] ?? 'monthly');
        $earned = 0.0; $paid = 0.0;
        if ($month && preg_match('/^\d{4}-\d{2}$/', $month)) {
            $monthEnd = date('Y-m-t', strtotime($month . '-01'));
            $monthStart = $month . '-01';
            $months = self::monthsSinceJoin($p['join_date'] ?? null, $month);
            $earned = $months > 0 ? round($rate, 2) : 0.0; // approx: this month's salary accrues once
            if ($p['person_type'] === 'employee') {
                $earned = (float)db_val("SELECT COALESCE(SUM(net_pay),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed' AND period_start <= ? AND period_end >= ?", [(int)$p['id'], $monthEnd, $monthStart]);
                $paid = (float)db_val("SELECT COALESCE(SUM(pp.amount),0) FROM payroll_payments pp JOIN payroll p ON p.id = pp.payroll_id WHERE p.employee_id = ? AND pp.status = 'paid' AND pp.date BETWEEN ? AND ?", [(int)$p['id'], $monthStart, $monthEnd]);
            } else {
                $earned = $months > 0 ? $rate : 0.0;
                $paid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid' AND date BETWEEN ? AND ?", [(int)$p['id'], $monthStart, $monthEnd]);
            }
            $remaining = round($earned - $paid, 2);
            $advances = 0.0;
            if ($p['person_type'] === 'employee') {
                $advances = (float)db_val("SELECT COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [(int)$p['id']]);
            } else {
                $advances = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [(int)$p['id']]);
            }
            return ['rate' => $rate, 'type' => $type, 'earned' => round($earned, 2), 'paid' => round($paid, 2), 'remaining' => $remaining, 'advances_out' => $advances, 'month' => $month];
        }
        // All-time
        $months = self::monthsSinceJoin($p['join_date'] ?? null);
        $earned = $months > 0 ? round($months * $rate, 2) : 0.0;
        if ($p['person_type'] === 'employee') {
            $paid = (float)db_val("SELECT COALESCE(SUM(amount_paid),0) FROM payroll WHERE employee_id = ? AND status <> 'reversed'", [(int)$p['id']]);
            $advances = (float)db_val("SELECT COALESCE(SUM(CASE WHEN amount > deducted THEN amount - deducted ELSE 0 END),0) FROM employee_advances WHERE employee_id = ? AND status = 'disbursed'", [(int)$p['id']]);
        } else {
            $paid = (float)db_val("SELECT COALESCE(SUM(amount),0) FROM salesman_salary_payments WHERE salesman_id = ? AND status = 'paid'", [(int)$p['id']]);
            $advances = (float)db_val("SELECT COALESCE(SUM(amount - deducted),0) FROM salesman_salary_advances WHERE salesman_id = ? AND status = 'paid' AND amount > deducted", [(int)$p['id']]);
        }
        return ['rate' => $rate, 'type' => $type, 'earned' => $earned, 'paid' => round($paid, 2), 'remaining' => round(max(0, $earned - $paid), 2), 'advances_out' => $advances, 'month' => null];
    }

    /** Unified active people list (employees + salesmen), sorted by name. */
    public static function allActive(): array {
        $people = [];
        foreach (db_all("SELECT id, code, name, phone, designation, kind, photo_path, join_date, salary_type, salary_rate, salary_frequency, 'employee' AS person_type FROM employees WHERE is_active = 1 AND COALESCE(is_archived, 0) = 0") as $e) {
            $people[] = self::shape((array)$e, 'employee');
        }
        foreach (db_all("SELECT id, code, name, phone, NULL AS designation, NULL AS kind, photo_path, join_date, 'monthly' AS salary_type, salary AS salary_rate, COALESCE(salary_frequency, 'monthly') AS salary_frequency, 'salesman' AS person_type FROM salesmen WHERE is_active = 1 AND COALESCE(is_archived, 0) = 0") as $s) {
            $people[] = self::shape((array)$s, 'salesman');
        }
        usort($people, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        return $people;
    }

    private static function shape(array $row, string $type): array {
        $designation = $type === 'employee' ? trim((string)($row['designation'] ?? '')) : 'Salesman';
        if ($designation === '') $designation = $type === 'employee' ? 'Employee' : 'Salesman';
        $kind = $type === 'salesman' ? 'salesman' : (($row['kind'] ?? 'employee') === 'labour' ? 'labour' : 'employee');
        return [
            'person_type' => $type,
            'kind' => $kind,
            'id' => (int)$row['id'],
            'code' => (string)$row['code'],
            'name' => (string)$row['name'],
            'phone' => (string)($row['phone'] ?? ''),
            'designation' => $designation,
            'photo_path' => $row['photo_path'] ?? null,
            'join_date' => $row['join_date'] ?? null,
            'salary_type' => $type === 'salesman' ? (string)($row['salary_frequency'] ?? 'monthly') : (string)($row['salary_type'] ?? 'monthly'),
            'salary_frequency' => (string)($row['salary_frequency'] ?? ($row['salary_type'] ?? 'monthly')),
            'salary_rate' => (float)($row['salary_rate'] ?? 0),
        ];
    }

    public static function find(string $type, int $id): ?array {
        if ($type === 'employee') {
            $r = db_one("SELECT id, code, name, phone, designation, kind, photo_path, join_date, salary_type, salary_rate, salary_frequency FROM employees WHERE id = ?", [$id]);
            return $r ? self::shape((array)$r, 'employee') : null;
        }
        if ($type === 'salesman') {
            $r = db_one("SELECT id, code, name, phone, NULL AS designation, NULL AS kind, photo_path, join_date, salary AS salary_rate, COALESCE(salary_frequency, 'monthly') AS salary_frequency FROM salesmen WHERE id = ?", [$id]);
            return $r ? self::shape((array)$r + ['salary_type' => 'monthly'], 'salesman') : null;
        }
        return null;
    }

    /** label mapping for kind filter */
    public static function kindLabel(string $kind): string {
        return ['labour' => 'Labour', 'employee' => 'Employee', 'salesman' => 'Salesman'][$kind] ?? 'Employee';
    }
}
