<?php

namespace App\Services\Extraction;

use RuntimeException;

/**
 * Turns whatever the model actually said into a PHP array.
 *
 * The system prompt asks for bare JSON, and most of the time that is what comes back. This
 * class exists for the rest of the time: fenced code blocks, a sentence of preamble, or a
 * `<think>` block from a reasoning model that ignored `/no_think`.
 *
 * There is nothing Laravel-specific in here and nothing worth learning from the technique —
 * it is a pile of regex that exists only because language models are unreliable narrators.
 * Treat it as a library you happen to own the source of.
 */
class JsonResponseParser
{
    /**
     * Tries progressively more desperate ways to find JSON in `$raw`, and returns the first
     * one that yields an array.
     *
     * @return array<string, mixed>
     */
    public function decode(string $raw): array
    {
        $cleaned = $this->stripThinkingBlocks($raw);

        if ($cleaned === '') {
            throw new RuntimeException(
                'Model returned nothing but a thinking block. It most likely hit the token '.
                'limit before it started answering.'
            );
        }

        // Rung 1: the happy path — the whole reply is JSON, exactly as instructed.
        $decoded = $this->tryDecode($cleaned);

        // Rung 2: the reply is wrapped in a ```json ... ``` markdown fence.
        if ($decoded === null && preg_match('/```(?:json)?\s*(.*?)\s*```/s', $cleaned, $fenced) === 1) {
            $decoded = $this->tryDecode($fenced[1]);
        }

        // Rung 3: there is chatter around the JSON. Take everything between the first `{`
        // and the last `}` and hope for the best.
        if ($decoded === null && preg_match('/\{.*\}/s', $cleaned, $braces) === 1) {
            $decoded = $this->tryDecode($braces[0]);
        }

        if ($decoded === null) {
            throw new RuntimeException("Could not find JSON in the model response:\n{$cleaned}");
        }

        return $decoded;
    }

    /**
     * Reasoning models emit a scratchpad before answering. Both patterns are needed: the
     * second catches an unclosed `<think>` left behind when the model ran out of tokens
     * mid-thought.
     */
    private function stripThinkingBlocks(string $raw): string
    {
        $raw = preg_replace('/<think>.*?<\/think>/s', '', $raw) ?? $raw;
        $raw = preg_replace('/<think>.*$/s', '', $raw) ?? $raw;

        return trim($raw);
    }

    /**
     * Returns null rather than throwing, so `decode()` can just try the next rung.
     *
     * The `is_array` check matters: `json_decode('42')` succeeds and hands back an int, which
     * would satisfy json_decode but not us.
     *
     * @return array<string, mixed>|null
     */
    private function tryDecode(string $candidate): ?array
    {
        $decoded = json_decode($candidate, associative: true);

        return is_array($decoded) ? $decoded : null;
    }
}
