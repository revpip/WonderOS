<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Claim;

interface ClaimCollaborationRepository
{
    public function assign(array $assignment): array;
    public function updateAssignment(string $uuid, string $status, string $actorUuid): array;
    public function comment(array $comment): array;
    public function assignmentsForClaim(string $claimWonderId): array;
    public function commentsForClaim(string $claimWonderId): array;
    public function workForUser(string $userUuid, int $limit = 100): array;
}
