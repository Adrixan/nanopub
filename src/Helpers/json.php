<?php

declare(strict_types=1);

/**
 * NanoPub JSON Utilities
 * 
 * JSON encoding/decoding with memory efficiency and safety.
 */

/**
 * Encode data to JSON with proper flags.
 * 
 * @param mixed $data Data to encode
 * @param int $options JSON encoding options
 * @return string JSON string
 * @throws JsonException If encoding fails
 */
function json_encode_safe(mixed $data, int $options = 0): string
{
    // Always include these flags for safety
    $options |= JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    
    return json_encode($data, $options);
}

/**
 * Decode JSON string safely.
 * 
 * @param string $json JSON string to decode
 * @param bool $assoc Return associative array instead of object
 * @return mixed Decoded data
 * @throws JsonException If decoding fails
 */
function json_decode_safe(string $json, bool $assoc = true): mixed
{
    return json_decode($json, $assoc, 512, JSON_THROW_ON_ERROR);
}

/**
 * Stream JSON output for large datasets.
 * Writes directly to php://output for memory efficiency.
 * 
 * @param iterable $data Data to encode
 * @return void
 */
function json_stream(iterable $data): void
{
    // Set headers if not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
    $output = fopen('php://output', 'wb');
    
    if ($output === false) {
        throw new RuntimeException('Failed to open output stream');
    }
    
    try {
        // Handle arrays
        if (is_array($data)) {
            fwrite($output, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            return;
        }
        
        // Handle iterables (generators, iterators)
        fwrite($output, '[');
        
        $first = true;
        foreach ($data as $item) {
            if (!$first) {
                fwrite($output, ',');
            }
            $first = false;
            
            fwrite($output, json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        
        fwrite($output, ']');
    } finally {
        fclose($output);
    }
}

/**
 * Create a JSON stream response for a generator.
 * Memory-efficient for large datasets.
 * 
 * @param Generator $generator Data generator
 * @return void
 */
function json_stream_generator(Generator $generator): void
{
    // Set headers if not already sent
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=UTF-8');
    }
    
    $output = fopen('php://output', 'wb');
    
    if ($output === false) {
        throw new RuntimeException('Failed to open output stream');
    }
    
    try {
        fwrite($output, '[');
        
        $first = true;
        foreach ($generator as $item) {
            if (!$first) {
                fwrite($output, ',');
            }
            $first = false;
            
            fwrite($output, json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }
        
        fwrite($output, ']');
    } finally {
        fclose($output);
    }
}
