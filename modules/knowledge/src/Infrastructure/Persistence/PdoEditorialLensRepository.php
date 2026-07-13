<?php

declare(strict_types=1);

namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use PDO;
use WonderOS\Knowledge\Graph\EditorialLens;
use WonderOS\Knowledge\Graph\EditorialLensRepository;
use WonderOS\Knowledge\Graph\GraphFilter;

final readonly class PdoEditorialLensRepository implements EditorialLensRepository
{
    public function __construct(private PDO $connection) {}

    public function get(string $slug): EditorialLens
    {
        $statement = $this->connection->prepare('SELECT slug, name, description, relationship_types, relationship_statuses, minimum_confidence, status FROM editorial_lenses WHERE slug = :slug');
        $statement->execute(['slug' => strtolower(trim($slug))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new DomainException(sprintf('Editorial lens "%s" does not exist.', $slug));
        }
        return $this->hydrate($row);
    }

    public function active(): array
    {
        $rows = $this->connection->query("SELECT slug, name, description, relationship_types, relationship_statuses, minimum_confidence, status FROM editorial_lenses WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): EditorialLens => $this->hydrate($row), $rows);
    }

    private function hydrate(array $row): EditorialLens
    {
        return new EditorialLens(
            (string)$row['slug'],
            (string)$row['name'],
            (string)$row['description'],
            new GraphFilter(
                $this->pgArray((string)$row['relationship_types']),
                $this->pgArray((string)$row['relationship_statuses']),
                (float)$row['minimum_confidence'],
            ),
            (string)$row['status'],
        );
    }

    /** @return list<string> */
    private function pgArray(string $value): array
    {
        $value = trim($value, '{}');
        return $value === '' ? [] : array_map('trim', str_getcsv($value));
    }
}
