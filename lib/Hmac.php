<?php
class Hmac {
    public static function validate(string $rawBody, string $signature): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $rawBody, WA_APP_SECRET);
        return hash_equals($expected, $signature);
    }
}
