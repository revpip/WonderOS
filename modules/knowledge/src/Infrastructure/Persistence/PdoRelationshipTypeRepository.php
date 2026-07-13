<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use PDO;
use WonderOS\Knowledge\Relationship\RelationshipType;
use WonderOS\Knowledge\Relationship\RelationshipTypeRepository;

final readonly class PdoRelationshipTypeRepository implements RelationshipTypeRepository
{
    public function __construct(private PDO $connection) {}

    public function get(string $type): RelationshipType
    {
        $statement = $this->connection->prepare('SELECT type, inverse_type, label, inverse_label, description, symmetric, status FROM relationship_types WHERE type = :type');
        $statement->execute(['type' => $this->normalise($type)]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new DomainException(sprintf('Relationship type "%s" is not in the approved vocabulary.', $type));
        }
        return $this->hydrate($row);
    }

    public function active(): array
    {
        $rows = $this->connection->query("SELECT type, inverse_type, label, inverse_label, description, symmetric, status FROM relationship_types WHERE status = 'active' ORDER BY label")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $row): RelationshipType => $this->hydrate($row), $rows);
    }

    private function hydrate(array $row): RelationshipType
    {
        return new RelationshipType((string)$row['type'], (string)$row['inverse_type'], (string)$row['label'], (string)$row['inverse_label'], (string)$row['description'], (bool)$row['symmetric'], (string)$row['status']);
    }

    private function normalise(string $value): string
    {
        $value = strtolower(trim($value));
        return trim(preg_replace('/[^a-z0-9]+/', '_', $value) ?? '', '_');
    }
}
