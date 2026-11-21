<?php
/**
 * Payroll engine — salary computation and period generation, extracted from the controller
 * so the exact same code runs in the web app and in tests.
 */
final class Payroll {

    /** Compute basic pay + overtime from attendance for a period. */
    public static function compute(array $emp, string $from, string $to): array {
        $empId = (int)$emp['id'];
        $type = $emp['salary_type'];
        $days = (float)db_val("SELECT COALESCE(SUM(CASE WHEN status='present' THEN 1 WHEN status='half_day' THEN 0.5 ELSE 0 END),0) FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?", [$empId, $from, $to]);
        $hours = (float)db_val('SELECT COALESCE(SUM(hours),0) FROM attendance WHERE employee_id = ? AND date BETWEEN ? AND ?', [$empId, $from, $to]);
        $periodDays = (int)(new DateTimeImmutable($from))->diff(new DateTimeImmutable($to))->days + 1;
        $basic = 0.0;
        switch ($type) {
            case 'monthly': case 'contract': $basic = (float)$emp['salary_rate']; break;
            case 'weekly':  $basic = (float)$emp['salary_rate'] * ($days > 0 ? $days / 6.0 : 0); break;  // 6 working days/week
            case 'daily':   $basic = $days * (float)$emp['salary_rate']; break;
            case 'hourly':  $basic = $hours * (float)$emp['salary_rate']; break;
            default:        $basic = (float)$emp['salary_rate'];
        }
        // Overtime = hours beyond 8/day at OT rate or 1.5× equivalent
        $otHours = max(0, $hours - $days * 8);
        $otRate = (float)($emp['overtime_rate'] ?? 0);
        if ($otRate <= 0) {
            $equivHourly = match ($type) {
                'monthly', 'contract' => (float)$emp['salary_rate'] / 208,
                'weekly'              => (float)$emp['salary_rate'] / 48,
                'daily'               => (float)$emp['salary_rate'] / 8,
                default               => (float)$emp['salary_rate'],
            };
            $otRate = $equivHourly * 1.5;
        }
        return ['basic' => round($basic, 2), 'overtime' => round($otHours * $otRate, 2), 'overtime_hours' => round($otHours, 2), 'hours_worked' => round($hours, 2), 'days' => $days, 'hours' => $hours, 'period_days' => $periodDays];
    }

    /** Reverse the deductions linked to a payslip (advances + loans). Used by reversal and safe regeneration. */
    public static function releaseDeductions(int $payrollId): void {
        foreach (db_all('SELECT * FROM payroll_deductions WHERE payroll_id = ?', [$payrollId]) as $d) {
            if ($d['source_type'] === 'advance') {
                db_exec('UPDATE employee_advances SET deducted = MAX(0, deducted - ?), payroll_id = NULL WHERE id = ?', [$d['amount'], $d['source_id']]);
            } else {
                db_exec("UPDATE loan_installments SET status = 'due', payroll_id = NULL WHERE id = ?", [$d['source_id']]);
                $li = db_one('SELECT amount, loan_id FROM loan_installments WHERE id = ?', [$d['source_id']]);
                if ($li) {
                    db_exec("UPDATE employee_loans SET recovered = MAX(0, recovered - ?), outstanding = amount - MAX(0, recovered - ?), paid_installments = MAX(0, paid_installments - 1), status = 'active' WHERE id = ?", [$li['amount'], $li['amount'], $li['loan_id']]);
                }
            }
        }
        db_exec('DELETE FROM payroll_deductions WHERE payroll_id = ?', [$payrollId]);
    }

    /**
     * Generate a payroll period for active employees. Weekly and monthly staff are supported independently —
     * pass $frequency ('monthly'|'weekly') to only generate that population, so periods never mix.
     * Exactly-once per (employee, period) — existing draft rows for that employee/period are recreated.
     * Returns number of payslips created.
     */
    public static function generate(string $from, string $to, int $userId, ?array $employeeIds = null, ?string $frequency = null): int {
        if ($frequency !== null && !in_array($frequency, ['monthly', 'weekly'], true)) {
            throw new RuntimeException(__('Invalid frequency.'));
        }
        if ($employeeIds === null && $frequency === null && db_val('SELECT 1 FROM payroll WHERE period_start = ? AND period_end = ? LIMIT 1', [$from, $to])) {
            throw new RuntimeException(__('Payroll already generated for this period. Reverse it first.'));
        }
        if ($employeeIds) {
            foreach ($employeeIds as $eid) {
                foreach (db_all('SELECT id FROM payroll WHERE employee_id = ? AND period_start = ? AND status = ?', [$eid, $from, 'draft']) as $row) {
                    self::releaseDeductions((int)$row['id']);
                    db_exec('DELETE FROM payroll WHERE id = ?', [(int)$row['id']]);
                }
            }
        }
        $created = 0;
        Database::transaction(function () use ($from, $to, $userId, $employeeIds, $frequency, &$created) {
            $sql = 'SELECT * FROM employees WHERE is_active = 1';
            $params = [];
            if ($frequency !== null) {
                $sql .= " AND COALESCE(salary_frequency, salary_type) = ?";
                $params[] = $frequency;
            }
            if ($employeeIds) {
                $marks = implode(',', array_fill(0, count($employeeIds), '?'));
                $sql .= " AND id IN ($marks)";
                $params = array_merge($params, array_map('intval', $employeeIds));
            }
            // Per-employee idempotency: skip employees already having a draft payslip for this period
            // (needed when generating monthly and weekly separately)
            $existing = [];
            foreach (db_all('SELECT employee_id FROM payroll WHERE period_start = ?', [$from]) as $row) {
                $existing[(int)$row['employee_id']] = true;
            }
            foreach (db_all($sql, $params) as $emp) {
                $empId = (int)$emp['id'];
                if (isset($existing[$empId])) continue;
                $calc = self::compute($emp, $from, $to);
                $basic = $calc['basic']; $overtime = $calc['overtime'];

                // Approved disbursed advances outstanding — configurable monthly deduction cap
                $advRows = db_all("SELECT * FROM employee_advances WHERE employee_id = ? AND status = 'disbursed' ORDER BY date, id", [$empId]);
                $advDeduct = 0.0;
                foreach ($advRows as $a) {
                    $lineOut = max(0, (float)$a['amount'] - (float)$a['deducted']);
                    if ($lineOut <= 0.004) continue;
                    $cap = $a['monthly_deduction'] !== null ? (float)$a['monthly_deduction'] : $lineOut;
                    $advDeduct += min($cap, $lineOut);
                }
                $advDeduct = min($advDeduct, round($basic + $overtime, 2));

                // Due loan installments whose due date is within the period
                $dueInstallments = db_all("SELECT li.* FROM loan_installments li JOIN employee_loans l ON l.id = li.loan_id WHERE l.employee_id = ? AND li.status = 'due' AND li.due_date <= ? ORDER BY li.installment_no", [$empId, $to]);
                $loanDeduct = 0.0;
                foreach ($dueInstallments as $li) $loanDeduct += (float)$li['amount'];

                $net = round($basic + $overtime - $advDeduct - $loanDeduct, 2);
                $pid = db_insert('payroll', [
                    'employee_id' => $empId, 'period_start' => $from, 'period_end' => $to,
                    'basic' => $basic, 'allowances' => 0, 'overtime' => $overtime, 'overtime_hours' => $calc['overtime_hours'], 'hours_worked' => $calc['hours_worked'],
                    'bonus' => 0, 'advances_deducted' => round($advDeduct, 2), 'loan_deduction' => round($loanDeduct, 2),
                    'other_deductions' => 0, 'net_pay' => $net, 'status' => 'draft', 'created_at' => now_local(),
                ]);

                // Apply advance deductions atomically + link to the payslip
                $remaining = $advDeduct;
                foreach ($advRows as $a) {
                    if ($remaining <= 0.004) break;
                    $lineOut = max(0, (float)$a['amount'] - (float)$a['deducted']);
                    $cap = $a['monthly_deduction'] !== null ? (float)$a['monthly_deduction'] : $lineOut;
                    $take = min($cap, $lineOut, $remaining);
                    if ($take <= 0) continue;
                    db_exec('UPDATE employee_advances SET deducted = deducted + ?, payroll_id = ? WHERE id = ?', [$take, $pid, $a['id']]);
                    db_insert('payroll_deductions', ['payroll_id' => $pid, 'source_type' => 'advance', 'source_id' => (int)$a['id'], 'amount' => $take]);
                    $remaining -= $take;
                }
                // Apply loan installments due (linked for reversal)
                foreach ($dueInstallments as $li) {
                    db_exec("UPDATE loan_installments SET status = 'deducted', payroll_id = ? WHERE id = ?", [$pid, $li['id']]);
                    db_insert('payroll_deductions', ['payroll_id' => $pid, 'source_type' => 'loan_installment', 'source_id' => (int)$li['id'], 'amount' => (float)$li['amount']]);
                    db_exec("UPDATE employee_loans SET recovered = recovered + ?, outstanding = MAX(0, amount - (recovered + ?)), paid_installments = paid_installments + 1 WHERE id = ?", [$li['amount'], $li['amount'], $li['loan_id']]);
                }
                db_exec("UPDATE employee_loans SET status = 'completed' WHERE employee_id = ? AND status = 'active' AND outstanding <= 0", [$empId]);
                $created++;
            }
        });
        audit('generate', 'payroll', null, "Payroll for $from → $to: $created employee(s)");
        return $created;
    }
}
