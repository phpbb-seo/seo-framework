<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use InvalidArgumentException;

/**
 * Detects ambiguous or conflicting patterns within a compiled pattern set.
 * Must be run at configuration compile time, not on every request.
 */
class PatternConflictDetector
{
    /**
     * Validates that the given set of compiled patterns do not produce ambiguous matches.
     *
     * @param array<string, CompiledUrlPattern> $patterns keyed by resource name
     * @throws InvalidArgumentException if a conflict is detected
     */
    public function detect(array $patterns): void
    {
        $regexes = [];
        foreach ($patterns as $resource => $compiled) {
            $regexes[$resource] = $compiled->regex;
        }

        $resourceNames = array_keys($regexes);

        // Check each pair: test if a hardcoded example from one matches the regex of another
        foreach ($resourceNames as $i => $resA) {
            foreach ($resourceNames as $j => $resB) {
                if ($i >= $j) {
                    continue;
                }

                $this->checkConflict($resA, $patterns[$resA], $resB, $patterns[$resB]);
            }
        }
    }

    private function checkConflict(
        string $nameA,
        CompiledUrlPattern $patA,
        string $nameB,
        CompiledUrlPattern $patB
    ): void {
        // Strategy: use the raw patterns to construct probe strings.
        // Replace tokens in patA with values that would also satisfy patB.
        // This is a structural check, not exhaustive.

        // If both patterns share the same static prefix (non-token part) and neither
        // has a distinguishing literal segment, they may conflict.
        $prefixA = $this->extractStaticPrefix($patA->rawPattern);
        $prefixB = $this->extractStaticPrefix($patB->rawPattern);

        // If one pattern's static prefix is a prefix of the other, and they differ only in tokens,
        // they may be ambiguous. We do a probe match: fill in tokens generically.
        $probeA = $this->buildProbe($patA->rawPattern, nameForB: $nameA);
        $probeB = $this->buildProbe($patB->rawPattern, nameForB: $nameB);

        // Test if the probe of A matches the regex of B, and vice versa
        if (preg_match($patB->regex, $probeA) && preg_match($patA->regex, $probeB)) {
            // Both probes match both patterns — genuinely ambiguous
            if ($prefixA === $prefixB) {
                throw new InvalidArgumentException(
                    "Ambiguous permalink patterns detected: '{$nameA}' and '{$nameB}' " .
                    "may match the same URLs. Patterns: '{$patA->rawPattern}' and '{$patB->rawPattern}'"
                );
            }
        }
    }

    private function extractStaticPrefix(string $rawPattern): string
    {
        // Take everything before the first token
        $pos = strpos($rawPattern, '{');
        if ($pos === false) {
            return $rawPattern;
        }
        return substr($rawPattern, 0, $pos);
    }

    private function buildProbe(string $rawPattern, string $nameForB): string
    {
        // Replace tokens with deterministic safe values
        $probe = preg_replace('/\{id\}/', '123', $rawPattern);
        $probe = preg_replace('/\{page\}/', '2', $probe);
        $probe = preg_replace('/\{slug\}/', 'sample-slug', $probe);
        return $probe ?? $rawPattern;
    }
}
