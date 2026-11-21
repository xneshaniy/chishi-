<?php
final class AuthController {
    public static function login(): void {
        if (Auth::check()) redirect('dashboard');
        $error = null;
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
            csrf_verify();
            [$ok, $error] = Auth::attempt(inp('username'), $_POST['password'] ?? '');
            if ($ok) redirect('dashboard');
        }
        $title = 'Sign In';
        require dirname(__DIR__) . '/views/auth/login.php';
    }

    public static function logout(): void {
        Auth::logout();
        redirect('auth/login');
    }
}
