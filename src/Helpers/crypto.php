<?php

declare(strict_types=1);

/**
 * NanoPub Cryptographic Utilities
 * 
 * Crypto functions for security, authentication, and ActivityPub signing.
 */

/**
 * Generate a secure random token.
 * 
 * @param int $bytes Number of random bytes (default 32)
 * @return string Hex-encoded token
 */
function generate_token(int $bytes = 32): string
{
    return bin2hex(random_bytes(max(1, $bytes)));
}

/**
 * Hash a password using bcrypt.
 * 
 * @param string $password Plain text password
 * @return string Hashed password
 */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify a password against a hash.
 * 
 * @param string $password Plain text password
 * @param string $hash Hashed password
 * @return bool True if password matches
 */
function verify_password(string $password, string $hash): bool
{
    return password_verify($password, $hash);
}

/**
 * Generate a signed hash for data integrity using HMAC-SHA256.
 * 
 * @param string $data Data to sign
 * @param string $secret Secret key for signing
 * @return string Hex-encoded signature
 */
function sign_data(string $data, string $secret): string
{
    return hash_hmac('sha256', $data, $secret);
}

/**
 * Verify signed data using HMAC-SHA256.
 * 
 * @param string $data Original data
 * @param string $signature Signature to verify
 * @param string $secret Secret key used for signing
 * @return bool True if signature is valid
 */
function verify_signed_data(string $data, string $signature, string $secret): bool
{
    $expected = sign_data($data, $secret);
    return hash_equals($expected, $signature);
}

/**
 * Generate RSA key pair for ActivityPub.
 * 
 * @return array{private: string, public: string} Key pair in PEM format
 * @throws RuntimeException If key generation fails
 */
function generate_rsa_key_pair(): array
{
    $config = [
        'digest_alg' => 'sha256',
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];
    
    $keyPair = openssl_pkey_new($config);
    
    if ($keyPair === false) {
        throw new RuntimeException('Failed to generate RSA key pair: ' . openssl_error_string());
    }
    
    // Export private key
    $privateKeyPem = '';
    openssl_pkey_export($keyPair, $privateKeyPem);
    
    // Export public key
    $keyDetails = openssl_pkey_get_details($keyPair);
    $publicKeyPem = $keyDetails['key'];
    
    return [
        'private' => $privateKeyPem,
        'public' => $publicKeyPem,
    ];
}

/**
 * Sign data with RSA private key using SHA-256.
 * 
 * @param string $data Data to sign
 * @param string $privateKeyPem Private key in PEM format
 * @return string Base64-encoded signature
 * @throws RuntimeException If signing fails
 */
function rsa_sign(string $data, string $privateKeyPem): string
{
    $privateKey = openssl_pkey_get_private($privateKeyPem);
    
    if ($privateKey === false) {
        throw new RuntimeException('Invalid private key: ' . openssl_error_string());
    }
    
    $signature = '';
    $result = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    
    if ($result === false) {
        throw new RuntimeException('Failed to sign data: ' . openssl_error_string());
    }
    
    return base64_encode($signature);
}

/**
 * Verify RSA signature using SHA-256.
 * 
 * @param string $data Original data
 * @param string $signature Base64-encoded signature
 * @param string $publicKeyPem Public key in PEM format
 * @return bool True if signature is valid
 */
function rsa_verify(string $data, string $signature, string $publicKeyPem): bool
{
    $publicKey = openssl_pkey_get_public($publicKeyPem);
    
    if ($publicKey === false) {
        return false;
    }
    
    $decodedSignature = base64_decode($signature, true);
    
    if ($decodedSignature === false) {
        return false;
    }
    
    $result = openssl_verify($data, $decodedSignature, $publicKey, OPENSSL_ALGO_SHA256);
    
    return $result === 1;
}
