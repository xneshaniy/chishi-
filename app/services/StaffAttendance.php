<?php
/**
 * Daily staff / salesman attendance — one mark per person per day (unique), corrections audited.
 * Storage: staff_attendance + staff_attendance_audit (person = employee (employees table) or salesman (salesmen table)).
 */
final class StaffAttendance {

    public const PRESENT = 'present';
    public const ABSENT = 'absent';
    public const LEAVE = 'leave';
    public const HALF_DAY = 'half_day';

    public static function validStatus(string $s): bool {
        return in_array($s, [self::PRESENT, self::ABSENT, self::LEAVE, self::HALF_DAY], true);
    }

    public static function validPersonType(string $t): bool {
        return in_array($t, ['employee', 'salesman'], true);
    }

    /** Column key for the given person type. */
    private static function col(string $personType): string {
        return $personType === 'salesman' ? 'salesman_id' : 'employee_id';
    }

    private static function exists(string $personType, int $personId): bool {
        $table = $personType === 'salesman' ? 'salesmen' : 'employees';
        return (bool)db_val("SELECT 1 FROM $table WHERE id = ? AND is_active = 1", [$personId]);
    }

    /** Mark (or correct) one person for a date. Returns [ok, saved_status]. Writes an audit row on every change. */
    public static function mark(int $personId, string $date, string $status, int $changedBy, string $note = '', string $personType = 'employee'): array {
        if (!self::validPersonType($personType)) return [false, __('Invalid person type.')];
        if (!self::validStatus($status)) return [false, __('Invalid attendance status.')];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = today();
        if ($personId <= 0 || !self::exists($personType, $personId)) {
            return [false, $personType === 'salesman' ? __('Salesman not found.') : __('Employee not found.')];
        }
        $col = self::col($personType);

        $existing = db_one("SELECT status FROM staff_attendance WHERE $col = ? AND date = ?", [$personId, $date]);
        Database::transaction(function () use ($existing, $col, $personType, $personId, $date, $status, $changedBy, $note) {
            $employeeId = $personType === 'salesman' ? null : $personId;
            $salesmanId = $personType === 'salesman' ? $personId : null;
            if ($existing) {
                $old = (string)$existing['status'];
                if ($old === $status && $note === '') return; // nothing changed
                db_exec("UPDATE staff_attendance SET status = ?, marked_by = ?, updated_at = ? WHERE $col = ? AND date = ?",
                    [$status, $changedBy ?: null, now_local(), $personId, $date]);
                db_insert('staff_attendance_audit', ['employee_id' => $employeeId, 'salesman_id' => $salesmanId, 'date' => $date, 'old_status' => $old, 'new_status' => $status, 'changed_by' => $changedBy ?: null, 'note' => $note !== '' ? $note : __('Correction'), 'changed_at' => now_local()]);
            } else {
                db_insert('staff_attendance', ['employee_id' => $employeeId, 'salesman_id' => $salesmanId, 'date' => $date, 'status' => $status, 'marked_by' => $changedBy ?: null, 'created_at' => now_local()]);
                db_insert('staff_attendance_audit', ['employee_id' => $employeeId, 'salesman_id' => $salesmanId, 'date' => $date, 'old_status' => null, 'new_status' => $status, 'changed_by' => $changedBy ?: null, 'note' => __('Initial mark'), 'changed_at' => now_local()]);
            }
        });
        return [true, $status];
    }

    /** All marks for a date as person_id => status. */
    public static function marksForDate(string $date, string $personType = 'employee'): array {
        if (!self::validPersonType($personType)) return [];
        $col = self::col($personType);
        $rows = db_all("SELECT $col AS pid, status FROM staff_attendance WHERE $col IS NOT NULL AND date = ?", [$date]);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['pid']] = $r['status'];
        return $out;
    }

    /** All marks for a date with the user who recorded/updated each of them:
     *  returns person_id => ['status' => ..., 'actor' => full_name|username|'—'] */
    public static function marksWithActor(string $date, string $personType = 'employee'): array {
        if (!self::validPersonType($personType)) return [];
        $col = self::col($personType);
        $rows = db_all("SELECT sa.$col AS pid, sa.status, u.full_name AS actor_name, u.username AS actor_username
                        FROM staff_attendance sa LEFT JOIN users u ON u.id = sa.marked_by
                        WHERE sa.$col IS NOT NULL AND sa.date = ?", [$date]);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['pid']] = ['status' => $r['status'], 'actor' => $r['actor_name'] ?: ($r['actor_username'] ?: null)];
        return $out;
    }

    /** Per-person latest activity (who recorded/updated each entry) for a date, across all types.
     *  Returns "employee:id" => actor name, "salesman:id" => actor name. */
    public static function actorsForDate(string $date): array {
        $out = [];
        foreach (['employee' => 'employee', 'salesman' => 'salesman'] as $t => $type) {
            foreach (self::marksWithActor($date, $type) as $pid => $m) $out[$t . ':' . $pid] = $m['actor'];
        }
        return $out;
    }

    /** Per-person counts between two dates: [person_id => [status => n]]. */
    public static function report(string $from, string $to, string $personType = 'employee'): array {
        if (!self::validPersonType($personType)) return [];
        $col = self::col($personType);
        $rows = db_all("SELECT $col AS pid, status, COUNT(*) AS n FROM staff_attendance WHERE $col IS NOT NULL AND date BETWEEN ? AND ? GROUP BY $col, status", [$from, $to]);
        $out = [];
        foreach ($rows as $r) $out[(int)$r['pid']][$r['status']] = (int)$r['n'];
        return $out;
    }

    /** Totals (present/absent/leave/half_day) for a date. */
    public static function dayTotals(string $date, string $personType = 'employee'): array {
        $col = self::col($personType);
        $rows = db_all("SELECT status, COUNT(*) AS n FROM staff_attendance WHERE $col IS NOT NULL AND date = ? GROUP BY status", [$date]);
        $out = ['present' => 0, 'absent' => 0, 'leave' => 0, 'half_day' => 0];
        foreach ($rows as $r) { if (isset($out[$r['status']])) $out[$r['status']] = (int)$r['n']; }
        return $out;
    }

    /** All-time totals for one person. */
    public static function personTotals(int $personId, string $personType = 'employee'): array {
        if (!self::validPersonType($personType)) return ['present' => 0, 'absent' => 0, 'leave' => 0, 'half_day' => 0];
        $col = self::col($personType);
        $row = db_one("SELECT
            COALESCE(SUM(CASE WHEN status='present' THEN 1 ELSE 0 END),0) AS present,
            COALESCE(SUM(CASE WHEN status='absent' THEN 1 ELSE 0 END),0) AS absent,
            COALESCE(SUM(CASE WHEN status='leave' THEN 1 ELSE 0 END),0) AS leave_cnt,
            COALESCE(SUM(CASE WHEN status='half_day' THEN 1 ELSE 0 END),0) AS half_day
            FROM staff_attendance WHERE $col = ?", [$personId]);
        return ['present' => (int)$row['present'], 'absent' => (int)$row['absent'], 'leave' => (int)$row['leave_cnt'], 'half_day' => (int)$row['half_day']];
    }

    /** Backward-compatible employee totals. */
    public static function employeeTotals(int $employeeId): array {
        return self::personTotals($employeeId, 'employee');
    }
}
