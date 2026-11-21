<?php
/**
 * Profile photo uploads — ONE canonical implementation.
 * Used for employee, salesman and labour profile photos.
 *
 * - Validation: jpg/jpeg/png/webp only, real-image verification via getimagesize(),
 *   size cap, randomized filename, stored OUTSIDE the web root (storage/uploads/),
 *   served only through permission-gated routes with proper cache headers.
 * - Failures are REPORTED (not silent) via self::$lastError for upload forms to flash.
 */
final class PhotoUpload {

    public const MAX_BYTES = 2 * 1024 * 1024; // 2 MB

    private static ?string $lastError = null;

    /** Last error message (upload, validation or storage). */
    public static function lastError(): ?string { return self::$lastError; }

    private static function fail(string $msg): ?string {
        self::$lastError = $msg;
        return null;
    }

    /** Validate + store one uploaded photo for an entity. Returns relative path or null on failure. */
    public static function store(array $file, string $subdir, int $entityId): ?string {
        self::$lastError = null;
        if (empty($file['name']) && (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)) {
            return self::fail('no file');
        }
        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return self::fail('upload error code ' . (int)$file['error']);
        }
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return self::fail('not a valid upload');
        }
        if ((int)$file['size'] > self::MAX_BYTES) {
            return self::fail('file is larger than ' . (self::MAX_BYTES / 1024 / 1024) . ' MB');
        }
        $ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp'];
        $ext = $allowed[$ext] ?? null;
        if ($ext === null) {
            return self::fail('only JPG, JPEG, PNG or WEBP images are allowed');
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || empty($info[0]) || empty($info[1])) {
            return self::fail('file is not a valid image');
        }
        $dir = dirname(__DIR__, 2) . '/storage/uploads/' . $subdir . '/' . (int)$entityId;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
            return self::fail('could not create storage directory');
        }
        $name = 'photo_' . (int)$entityId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dest = $dir . '/' . $name;
        // Normalize to a ≤256px thumbnail (keeps inline data URIs tiny and speeds every screen)
        $thumb = self::makeThumbnail($file['tmp_name'], (int)$info[0], (int)$info[1], $ext);
        $raw = $thumb['raw'] ?? null;
        if ($raw !== null) {
            if (file_put_contents($dest, $raw) === false) {
                return self::fail('could not save the uploaded file');
            }
        } else {
            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                return self::fail('could not save the uploaded file');
            }
        }
        return $subdir . '/' . (int)$entityId . '/' . $name;
    }

    /** Re-encode upload as a capped thumbnail and return raw bytes, or null when it cannot be processed. */
    private static function makeThumbnail(string $tmpPath, int $w, int $h, string $ext): ?array {
        if (!function_exists('imagecreatetruecolor') || $w <= 0 || $h <= 0) return null;
        $max = 256;
        $scale = min(1.0, $max / max($w, $h));
        $tw = max(1, (int)round($w * $scale));
        $th = max(1, (int)round($h * $scale));
        $mime = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? null;
        if (!$mime) return null;
        switch ($mime) {
            case 'image/jpeg': $src = imagecreatefromjpeg($tmpPath); break;
            case 'image/png':  $src = imagecreatefrompng($tmpPath); break;
            case 'image/webp': $src = function_exists('imagecreatefromwebp') ? imagecreatefromwebp($tmpPath) : false; break;
            default: return null;
        }
        if (!$src) return null;
        $thumb = imagecreatetruecolor($tw, $th);
        if ($mime === 'image/png') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
            imagefill($thumb, 0, 0, $transparent);
        }
        imagecopyresampled($thumb, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        ob_start();
        if ($mime === 'image/png') { imagepng($thumb, null, 6); }
        elseif ($mime === 'image/webp') { imagewebp($thumb, null, 82); }
        else { imagejpeg($thumb, null, 82); }
        $raw = ob_get_clean();
        if (PHP_VERSION_ID < 80000) {
            @imagedestroy($src);
            @imagedestroy($thumb);
        }
        if ($raw === null || $raw === false) return null;
        return ['raw' => $raw];
    }

    /** Stream a stored photo with strong cache semantics (private + revalidate; ETag based on mtime+size). */
    public static function stream(?string $rel, string $defaultName = 'photo.jpg'): void {
        if (!$rel) { http_response_code(404); exit('Not found'); }
        $path = dirname(__DIR__, 2) . '/storage/uploads/' . $rel;
        if (!is_file($path)) { http_response_code(404); exit('File not found'); }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? 'application/octet-stream';
        $etag = md5($path . '|' . filemtime($path) . '|' . filesize($path));
        $inm = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
        if ($inm !== '' && trim($inm, '"') === $etag) {
            http_response_code(304);
            exit;
        }
        header('Content-Type: ' . $mime);
        header('Content-Disposition: inline; filename="' . str_replace(['"', '\\'], '_', basename($defaultName)) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, max-age=31536000, immutable');
        header('ETag: "' . $etag . '"');
        readfile($path);
        exit;
    }
}
