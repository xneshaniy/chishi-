<?php
/**
 * Unified Attendance section — one combined attendance board for salesmen + all employees/labour.
 * Reuses the existing staff_attendance storage (now generalized) and the ERP's approval/permission model.
 */
final class AttendanceController {

    private const STATUS_LABELS = ['present', 'absent', 'half_day'];

    /* ============ Combined attendance board ============ */

    public static function index(): void {
        $date = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('date', '')) ? inp('date') : today();
        $q = trim(inp('q'));
        $kind = in_array(inp('kind'), ['all', 'salesman', 'labour', 'employee'], true) ? inp('kind') : 'all';
        $designation = trim(inp('designation'));

        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            require_post(); csrf_verify();
            if (!Auth::can('attendance.mark')) { http_response_code(403); exit('Forbidden'); }
            self::saveAll($date, $q, $kind, $designation);
            return;
        }
        $people = PeopleDirectory::allActive();
        $rows = [];
        $totalP = 0; $totalA = 0; $totalH = 0; $marked = 0;
        $marksEmp = StaffAttendance::marksForDate($date, 'employee');
        $marksSm = StaffAttendance::marksForDate($date, 'salesman');
        $actors = StaffAttendance::actorsForDate($date);

        foreach ($people as $p) {
            if ($kind !== 'all' && $p['kind'] !== $kind) continue;
            if ($designation !== '' && strcasecmp($p['designation'], $designation) !== 0) continue;
            if ($q !== '') {
                $hay = strtolower($p['name'] . ' ' . $p['code'] . ' ' . $p['phone'] . ' ' . $p['designation']);
                if (strpos($hay, strtolower($q)) === false) continue;
            }
            $type = $p['person_type'];
            $status = $type === 'salesman' ? ($marksSm[(int)$p['id']] ?? '') : ($marksEmp[(int)$p['id']] ?? '');
            $p['actor'] = $actors[$type . ':' . (int)$p['id']] ?? null;
            if ($status === 'present') { $totalP++; $marked++; }
            elseif ($status === 'absent') { $totalA++; $marked++; }
            elseif ($status === 'leave') { $marked++; }
            elseif ($status === 'half_day') { $totalH++; $marked++; }
            $row = $p;
            $row['status'] = $status;
            $row['att_totals'] = StaffAttendance::personTotals((int)$p['id'], $type);
            $rows[] = $row;
        }

        $designations = [];
        foreach ($people as $p) { if ($kind === 'all' || $p['kind'] === $kind) $designations[$p['designation']] = true; }
        ksort($designations);
        $designations = array_keys($designations);

        view('attendance/index', [
            'rows' => $rows, 'date' => $date, 'q' => $q, 'kind' => $kind, 'designation' => $designation,
            'designations' => $designations,
            'totalP' => $totalP, 'totalA' => $totalA, 'totalH' => $totalH, 'marked' => $marked,
            'title' => __('Attendance'),
        ]);
    }

    /** Bulk save: upsert each marked person for the given date (unique per person+date). */
    private static function saveAll(string $date, string $q, string $kind, string $designation): void {
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $statuses = $_POST['status'] ?? [];
        $saved = 0; $failed = 0;
        Database::transaction(function () use ($statuses, $date, $userId, &$saved, &$failed) {
            foreach ($statuses as $key => $status) {
                if (!is_string($status)) continue;
                [$type, $pid] = array_pad(explode(':', (string)$key, 2), 2, '');
                $pid = (int)$pid;
                if (!in_array($type, ['employee', 'salesman'], true) || $pid <= 0) continue;
                if (!in_array($status, self::STATUS_LABELS, true)) continue;
                [$ok] = StaffAttendance::mark($pid, $date, $status, $userId, '', $type);
                $ok ? $saved++ : $failed++;
            }
        });
        if ($saved > 0) {
            try {
                Notifications::notifyAttendanceUpdated($date, sprintf(__('Marked: %d saved, %d unchanged/failed'), $saved, $failed));
            } catch (Throwable $e) { error_log('notify attendance: ' . $e->getMessage()); }
        }
        flash('success', sprintf(__('Attendance saved for %d people for %s.'), $saved, date_display($date)));
        redirect('attendance', ['date' => $date, 'q' => $q, 'kind' => $kind, 'designation' => $designation]);
    }

    /* ============ Monthly / range attendance report ============ */

    public static function report(): void {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', '')) ? inp('from') : date('Y-m-01');
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', '')) ? inp('to') : today();
        $q = trim(inp('q'));
        $kind = in_array(inp('kind'), ['all', 'salesman', 'labour', 'employee'], true) ? inp('kind') : 'all';
        $designation = trim(inp('designation'));

        $people = PeopleDirectory::allActive();
        $marksEmp = StaffAttendance::report($from, $to, 'employee');
        $marksSm = StaffAttendance::report($from, $to, 'salesman');
        $rows = [];
        $totPres = 0; $totAbs = 0; $totHalf = 0; $totLeave = 0; $totNet = 0.0;

        foreach ($people as $p) {
            if ($kind !== 'all' && $p['kind'] !== $kind) continue;
            if ($designation !== '' && strcasecmp($p['designation'], $designation) !== 0) continue;
            if ($q !== '') {
                $hay = strtolower($p['name'] . ' ' . $p['code'] . ' ' . $p['phone'] . ' ' . $p['designation']);
                if (strpos($hay, strtolower($q)) === false) continue;
            }
            $type = $p['person_type'];
            $m = $type === 'salesman' ? ($marksSm[(int)$p['id']] ?? []) : ($marksEmp[(int)$p['id']] ?? []);
            $present = (int)($m['present'] ?? 0);
            $absent = (int)($m['absent'] ?? 0);
            $half = (int)($m['half_day'] ?? 0);
            $leave = (int)($m['leave'] ?? 0);
            $net = round($present + ($half * 0.5), 2);
            $totPres += $present; $totAbs += $absent; $totHalf += $half; $totLeave += $leave; $totNet += $net;
            $rows[] = $p + ['present' => $present, 'absent' => $absent, 'half_day' => $half, 'leave' => $leave, 'net_days' => $net];
        }

        $designations = [];
        foreach ($people as $p) { if ($kind === 'all' || $p['kind'] === $kind) $designations[$p['designation']] = true; }
        ksort($designations);
        $designations = array_keys($designations);

        view('attendance/report', [
            'rows' => $rows, 'from' => $from, 'to' => $to, 'q' => $q, 'kind' => $kind, 'designation' => $designation,
            'designations' => $designations,
            'totPres' => $totPres, 'totAbs' => $totAbs, 'totHalf' => $totHalf, 'totLeave' => $totLeave, 'totNet' => $totNet,
            'title' => __('Attendance Report'),
        ]);
    }

    /* ============ Attendance change history (who recorded/updated what and when) ============ */

    public static function history(): void {
        if (!Auth::can('attendance.mark')) { http_response_code(403); exit('Forbidden'); }
        $q = trim(inp('q'));
        $type = in_array(inp('type'), ['all', 'employee', 'salesman'], true) ? inp('type') : 'all';
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('from', '')) ? inp('from') : date('Y-m-d', strtotime('-30 days'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', inp('to', '')) ? inp('to') : today();
        $where = ' WHERE a.date BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($type === 'employee') { $where .= ' AND a.employee_id IS NOT NULL'; }
        elseif ($type === 'salesman') { $where .= ' AND a.salesman_id IS NOT NULL'; }
        if ($q !== '') {
            $where .= ' AND (e.name LIKE ? OR e.code LIKE ? OR s.name LIKE ? OR s.code LIKE ?)';
            $like = "%$q%";
            array_push($params, $like, $like, $like, $like);
        }
        $sql = "SELECT a.*,
                    COALESCE(e.name, s.name) AS person_name, COALESCE(e.code, s.code) AS person_code,
                    CASE WHEN a.employee_id IS NOT NULL THEN 'employee' ELSE 'salesman' END AS person_type,
                    u.full_name AS actor_name, u.username AS actor_username
                FROM staff_attendance_audit a
                LEFT JOIN employees e ON e.id = a.employee_id
                LEFT JOIN salesmen s ON s.id = a.salesman_id
                LEFT JOIN users u ON u.id = a.changed_by
                $where ORDER BY a.id DESC";
        $p = paginate($sql, $params, intv(inp('page', 1)), 40);
        view('attendance/history', ['p' => $p, 'q' => $q, 'type' => $type, 'from' => $from, 'to' => $to, 'title' => __('Attendance History')]);
    }
}
