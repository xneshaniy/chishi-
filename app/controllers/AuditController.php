<?php
final class AuditController {
    public static function index(): void {
        $entity = inp('entity'); $user = inp('user_id'); $action = inp('action');
        $from = inp('from', date('Y-m-d', strtotime('-30 days'))); $to = inp('to', today());
        $where = ['a.created_at >= ?', 'a.created_at <= ?'];
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];
        if ($entity !== '') { $where[] = 'entity = ?'; $params[] = $entity; }
        if ($action !== '') { $where[] = 'action = ?'; $params[] = $action; }
        if ($user !== '') { $where[] = 'user_id = ?'; $params[] = (int)$user; }
        $sql = 'SELECT a.*, u.username FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id WHERE ' . implode(' AND ', $where) . ' ORDER BY a.id DESC';
        $p = paginate($sql, $params, intv(inp('page', 1)), 30);
        $users = db_all('SELECT DISTINCT u.id, u.username FROM users u JOIN audit_logs a ON a.user_id = u.id ORDER BY u.username');
        view('audit/index', ['p' => $p, 'filters' => compact('entity','user','action','from','to'), 'users' => $users, 'title' => 'Audit Log']);
    }
}
