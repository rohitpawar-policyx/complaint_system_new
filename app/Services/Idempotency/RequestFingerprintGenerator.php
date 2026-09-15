<?php

namespace App\Services\Idempotency;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Turns a request into a deterministic fingerprint: the same logical
 * request always produces the same hash, and a genuinely different one
 * (almost) always produces a different hash.
 *
 * Deliberately generic - this class has no knowledge of "complaint",
 * "payment_proof", or any other endpoint-specific field name, which is
 * what makes it reusable for POST /payments, POST /registrations, etc.
 * without modification.
 */
class RequestFingerprintGenerator
{
    /**
     * Fields that must never affect the fingerprint: the idempotency key
     * itself (using it as part of its own fingerprint would be circular),
     * and Laravel/HTML-form framework fields that are not part of the
     * caller's actual logical request.
     */
    private const EXCLUDED_FIELDS = ['idempotency_key', '_token', '_method'];

    public function generate(Request $request): string
    {
        // $request->except() is based on all(), which Laravel merges file
        // inputs into - without also excluding file field names here, a
        // raw UploadedFile object would land in $fields and get silently
        // stringified by normalize() via SplFileInfo::__toString(), which
        // returns the TEMP PATH. Files are already handled correctly,
        // separately, by hashFiles() below.
        $fileFields = array_keys($request->allFiles());
        $fields = $this->normalize($request->except([...self::EXCLUDED_FIELDS, ...$fileFields]));
        $files = $this->hashFiles($request->allFiles());

        // JSON with alphabetically-sorted top-level keys ("fields" before
        // "files" is already alphabetical, but ksort inside normalize()
        // handles every nested level too) - not relying on PHP array
        // insertion order, which is exactly the "unstable ordering" the
        // fingerprint must not depend on.
        $canonical = json_encode(['fields' => $fields, 'files' => $files], JSON_THROW_ON_ERROR);

        return hash('sha256', $canonical);
    }

    /**
     * Recursively sorts associative array keys for determinism. List arrays
     * (sequential 0,1,2... keys) are left in their original element order -
     * ksort on a list is a no-op anyway, and preserving list order is the
     * more conservative choice: two lists submitted in a different order
     * are treated as different requests rather than silently equated.
     */
    private function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            // Scalars are normalized as strings so e.g. "1" and 1 fingerprint
            // identically - HTML form submissions are strings on the wire
            // regardless of how a caller's typed value looks in PHP.
            return $value === null ? null : (string) $value;
        }

        $normalized = array_map(fn ($v) => $this->normalize($v), $value);
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param  array<string, UploadedFile|UploadedFile[]>  $files
     * @return array<string, string|array<int, string>>
     */
    private function hashFiles(array $files): array
    {
        $hashed = [];

        foreach ($files as $field => $value) {
            if (is_array($value)) {
                $hashed[$field] = collect($value)
                    ->filter(fn ($file) => $file instanceof UploadedFile && $file->isValid())
                    ->map(fn (UploadedFile $file) => $this->hashFile($file))
                    ->sort()
                    ->values()
                    ->all();

                continue;
            }

            if ($value instanceof UploadedFile && $value->isValid()) {
                $hashed[$field] = $this->hashFile($value);
            }
        }

        ksort($hashed);

        return $hashed;
    }

    /**
     * Content hash, NEVER the temp path - PHP allocates a fresh tmp file
     * per request even for a byte-identical re-upload of the same file, so
     * hashing the path would make every retry fingerprint as "different".
     */
    private function hashFile(UploadedFile $file): string
    {
        return hash_file('sha256', $file->getRealPath());
    }
}
