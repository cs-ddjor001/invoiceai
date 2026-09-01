<?php

namespace App\Services\Extraction;

use RuntimeException;

class JsonResponseParser
{
    /**
     * @return array<string, mixed>
     */
    public function parse(string $raw): array
    {
        if ($raw === '') {
            throw new RuntimeException(
                'The model returned an empty response.'
            );
        }

        if (strpos($raw, '{') === false || strpos($raw, '}') === false) {
            throw new RuntimeException(
                'Model returned malformed JSON: '.$raw
            );
        }

        $firstBrace = strpos($raw, '{');
        $lastBrace = strrpos($raw, '}');

        $json = substr($raw, $firstBrace, $lastBrace - $firstBrace + 1);

        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException(
                'Model returned unparseable JSON: '.$raw
            );
        }

        return $decoded;
    }
}
