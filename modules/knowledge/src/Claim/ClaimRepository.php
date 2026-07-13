<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Claim;

interface ClaimRepository
{
    public function nextIdentity(string $prefix): string;
    public function createSource(array $source): array;
    public function createClaim(array $claim): array;
    public function addEvidence(array $evidence): array;
    public function claimsForEntity(string $entityWonderId): array;
    public function evidenceForClaim(string $claimWonderId): array;
    public function source(string $sourceWonderId): ?array;
    public function claim(string $claimWonderId): ?array;
    public function reviseClaim(string $claimWonderId, array $changes, int $expectedRevision, string $changedBy, ?string $changeNote): array;
    public function claimHistory(string $claimWonderId): array;
    /** @param list<string> $statuses @return list<array<string,mixed>> */
    public function reviewQueue(array $statuses, int $limit = 100): array;
}
