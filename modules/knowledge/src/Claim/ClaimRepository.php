<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Claim;

interface ClaimRepository
{
    public function nextIdentity(string $prefix): string;
    /** @param array<string,mixed> $source */
    public function createSource(array $source): array;
    /** @param array<string,mixed> $claim */
    public function createClaim(array $claim): array;
    /** @param array<string,mixed> $evidence */
    public function addEvidence(array $evidence): array;
    /** @return list<array<string,mixed>> */
    public function claimsForEntity(string $entityWonderId): array;
    /** @return list<array<string,mixed>> */
    public function evidenceForClaim(string $claimWonderId): array;
    public function source(string $sourceWonderId): ?array;
    public function claim(string $claimWonderId): ?array;
    /** @param array<string,mixed> $changes */
    public function reviseClaim(string $claimWonderId, array $changes, int $expectedRevision, string $changedBy, ?string $changeNote): array;
    /** @return list<array<string,mixed>> */
    public function claimHistory(string $claimWonderId): array;
}