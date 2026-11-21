<?php
/**
 * Notifications — in-app inbox (full history), unread badge polling endpoint,
 * mark read/unread, and per-user sound/desktop popup preferences.
 * All endpoints are scoped to the signed-in user. No cross-user access possible.
 */
final class NotificationsController {

    public static function index(): void {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $filter = in_array(inp('filter'), ['all', 'unread', 'read'], true) ? inp('filter') : 'all';
        $p = Notifications::history($uid, $filter, intv(inp('page', 1)), 20);
        view('notifications/index', ['p' => $p, 'filter' => $filter, 'prefs' => Notifications::prefs($uid), 'title' => 'Notifications']);
    }

    /** JSON: { unread, items: [...] } — polled by the topbar bell every 15 s. */
    public static function unread(): void {
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $rows = Notifications::fetchDropdown($uid, 8);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'unread' => Notifications::unreadCount($uid),
            'items' => array_map(function ($r) {
                return [
                    'id' => (int)$r['id'], 'type' => $r['type'], 'title' => $r['title'], 'body' => $r['body'],
                    'link' => $r['link'], 'is_read' => (int)$r['is_read'],
                    'created_at' => dt_display($r['created_at']),
                    'when_iso' => str_replace(' ', 'T', $r['created_at']),
                ];
            }, $rows),
        ]);
        exit;
    }

    /* ============ POST actions ============ */

    public static function mark(): void {
        require_post(); csrf_verify();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $action = inp('action'); // read | unread | all-read
        if ($action === 'all-read') {
            Notifications::markAllRead($uid);
        } else {
            $id = intv(inp('id'));
            Notifications::markRead($uid, $id, $action !== 'unread');
        }
        if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' || inp('json') === '1') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'unread' => Notifications::unreadCount($uid)]);
            exit;
        }
        $back = $_SERVER['HTTP_REFERER'] ?? url('notifications');
        header('Location: ' . $back);
        exit;
    }

    public static function toggleSound(): void {
        require_post(); csrf_verify();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $cur = Notifications::prefs($uid);
        Notifications::setPref($uid, 'sound', !$cur['sound']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('notifications')));
        exit;
    }

    public static function toggleDesktop(): void {
        require_post(); csrf_verify();
        $uid = (int)($_SESSION['user_id'] ?? 0);
        $cur = Notifications::prefs($uid);
        Notifications::setPref($uid, 'desktop', !$cur['desktop']);
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? url('notifications')));
        exit;
    }
}
