<?php

declare(strict_types=1);

/**
 * NanoPub Input Validation Utilities
 * 
 * Input validation utilities for secure data handling.
 */

/**
 * Validate email format.
 * 
 * @param string $email Email to validate
 * @return bool True if valid email format
 */
function is_valid_email(string $email): bool
{
    if ($email === '') {
        return false;
    }
    
    // Check length (RFC 5321 max 254 characters)
    if (mb_strlen($email, 'UTF-8') > 254) {
        return false;
    }
    
    // Use PHP's built-in validation
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate URL format.
 * 
 * @param string $url URL to validate
 * @return bool True if valid URL format
 */
function is_valid_url(string $url): bool
{
    if ($url === '') {
        return false;
    }
    
    // Check length (reasonable limit)
    if (mb_strlen($url, 'UTF-8') > 2048) {
        return false;
    }
    
    // Use PHP's built-in validation
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Validate username format.
 * Rules: alphanumeric and underscores, 1-30 characters.
 * 
 * @param string $username Username to validate
 * @return bool True if valid username format
 */
function is_valid_username(string $username): bool
{
    if ($username === '') {
        return false;
    }
    
    $length = mb_strlen($username, 'UTF-8');
    
    // Check length
    if ($length > 30) {
        return false;
    }
    
    // Check format: alphanumeric and underscores only
    return preg_match('/^[a-zA-Z0-9_]+$/', $username) === 1;
}

/**
 * Validate password strength.
 * Rules: minimum 8 characters, at least one uppercase, one lowercase, one number.
 * 
 * @param string $password Password to validate
 * @return array{valid: bool, errors: array<int, string>} Validation result
 */
function validate_password(string $password): array
{
    $errors = [];
    
    // Minimum length
    if (mb_strlen($password, 'UTF-8') < 8) {
        $errors[] = 'Password must be at least 8 characters long';
    }
    
    // Maximum length (prevent DoS)
    if (mb_strlen($password, 'UTF-8') > 256) {
        $errors[] = 'Password must not exceed 256 characters';
    }
    
    // Check for at least one lowercase letter
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Password must contain at least one lowercase letter';
    }
    
    // Check for at least one uppercase letter
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Password must contain at least one uppercase letter';
    }
    
    // Check for at least one digit
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Password must contain at least one number';
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Sanitize input string.
 * Removes leading/trailing whitespace and normalizes unicode.
 * 
 * @param string $input Input to sanitize
 * @return string Sanitized string
 */
function sanitize_input(string $input): string
{
    // Normalize unicode (NFC form)
    $input = \Normalizer::normalize($input, \Normalizer::FORM_C) ?? $input; // @phpstan-ignore nullCoalesce.expr
    
    // Trim whitespace
    $input = trim($input);
    
    // Strip control characters except newlines and tabs
    $input = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $input) ?? $input;
    
    return $input;
}

/**
 * Validate integer is within range.
 * 
 * @param int $value Value to check
 * @param int $min Minimum value (inclusive)
 * @param int $max Maximum value (inclusive)
 * @return bool True if within range
 */
function is_int_in_range(int $value, int $min, int $max): bool
{
    return $value >= $min && $value <= $max;
}

/**
 * Validate string length is within range.
 * 
 * @param string $value String to check
 * @param int $min Minimum length (inclusive)
 * @param int $max Maximum length (inclusive)
 * @return bool True if within range
 */
function is_length_in_range(string $value, int $min, int $max): bool
{
    $length = mb_strlen($value, 'UTF-8');
    return $length >= $min && $length <= $max;
}

/**
 * Validate file upload.
 * 
 * @param array $file $_FILES array element
 * @param array<int, string> $allowedTypes Allowed MIME types
 * @param int $maxSize Maximum size in bytes
 * @return array{valid: bool, error: string|null} Validation result
 */
function validate_file_upload(array $file, array $allowedTypes, int $maxSize): array
{
    // Check for upload errors
    $errorCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    
    if ($errorCode !== UPLOAD_ERR_OK) {
        return [
            'valid' => false,
            'error' => match ($errorCode) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload blocked by extension',
                default => 'Unknown upload error',
            },
        ];
    }
    
    // Check file size
    $fileSize = $file['size'] ?? 0;
    if ($fileSize <= 0) {
        return ['valid' => false, 'error' => 'File is empty'];
    }
    
    if ($fileSize > $maxSize) {
        return ['valid' => false, 'error' => 'File exceeds maximum allowed size'];
    }
    
    // Check MIME type
    $mimeType = $file['type'] ?? '';
    
    // Also check using finfo for more reliable detection
    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName !== '' && file_exists($tmpName)) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $detectedType = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            if ($detectedType !== false) {
                $mimeType = $detectedType;
            }
        }
    }
    
    if (!in_array($mimeType, $allowedTypes, true)) {
        return ['valid' => false, 'error' => 'File type not allowed'];
    }
    
    return ['valid' => true, 'error' => null];
}
