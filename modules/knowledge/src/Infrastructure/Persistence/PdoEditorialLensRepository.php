<?php

declare(strict_types=1);
namespace WonderOS\Knowledge\Infrastructure\Persistence;

use DomainException;
use JsonException;
use PDO;
use PDOException;
use WonderOS\Knowledge\Graph\EditorialLens;
use WonderOS\Knowledge\Graph\EditorialLensRepository;
use WonderOS\Knowledge\Graph\GraphFilter;

final readonly class PdoEditorialLensRepository implements EditorialLensRepository
{
    public function __construct(private PDO $connection) {}

    public function get(string $slug): EditorialLens
    {
        $statement = $this->connection->prepare('SELECT slug, name, description, relationship_types, relationship_statuses, minimum_confidence, status, revision, updated_by FROM editorial_lenses WHERE slug = :slug');
        $statement->execute(['slug' => strtolower(trim($slug))]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            throw new DomainException(sprintf('Editorial lens "%s" does not exist.', $slug));
        }
        return $this->hydrate($row);
    }

    public function active(): array
    {
        $rows = $this->connection->query("SELECT slug, name, description, relationship_types, relationship_statuses, minimum_confidence, status, revision, updated_by FROM editorial_lenses WHERE status = 'active' ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row): EditorialLens => $this->hydrate($row), $rows);
    }

    public function save(EditorialLens $lens, ?int $expectedRevision = null): void
    {
        $this->connection->beginTransaction();
        try {
            if ($expectedRevision === null) {
                $statement = $this->connection->prepare(
                    'INSERT INTO editorial_lenses (slug,name,description,relationship_types,relationship_statuses,minimum_confidence,status,revision,updated_by)
                     VALUES (:slug,:name,:description,:types,:statuses,:confidence,:status,:revision,:updated_by)'
                );
            } else {
                $statement = $this->connection->prepare(
                    'UPDATE editorial_lenses
                     SET name=:name, description=:description, relationship_types=:types, relationship_statuses=:statuses,
                         minimum_confidence=:confidence, status=:status, revision=:revision, updated_by=:updated_by, updated_at=NOW()
                     WHERE slug=:slug AND revision=:expected_revision'
                );
            }

            $parameters = [
                'slug' => $lens->slug,
                'name' => $lens->name,
                'description' => $lens->description,
                'types' => $this->pgArray($lens->filter->types),
                'statuses' => $this->pgArray($lens->filter->statuses),
                'confidence' => $lens->filter->minimumConfidence,
                'status' => $lens->status,
                'revision' => $lens->revision,
                'updated_by' => $lens->updatedBy,
            ];
            if ($expectedRevision !== null) {
                $parameters['expected_revision'] = $expectedRevision;
            }
            $statement->execute($parameters);

            if ($expectedRevision !== null && $statement->rowCount() !== 1) {
                throw new DomainException('Editorial lens revision conflict. Reload the lens before saving.');
            }

            $history = $this->connection->prepare(
                'INSERT INTO editorial_lens_revisions (lens_slug,revision,snapshot,changed_by)
                 VALUES (:slug,:revision,CAST(:snapshot AS JSONB),:changed_by)'
            );
            $history->execute([
                'slug' => $lens->slug,
                'revision' => $lens->revision,
                'snapshot' => json_encode($this->snapshot($lens), JSON_THROW_ON_ERROR),
                'changed_by' => $lens->updatedBy,
            ]);

            $this->connection->commit();
        } catch (PDOException $exception) {
            $this->connection->rollBack();
            if ($exception->getCode() === '23505') {
                throw new DomainException(sprintf('Editorial lens "%s" already exists.', $lens->slug), previous: $exception);
            }
            throw $exception;
        } catch (JsonException|DomainException $exception) {
            $this->connection->rollBack();
            throw $exception;
        }
    }

    public function history(string $slug): array
    {
        $statement = $this->connection->prepare(
            'SELECT revision, snapshot, changed_by, changed_at
             FROM editorial_lens_revisions WHERE lens_slug=:slug ORDER BY revision DESC'
        );
        $statement->execute(['slug' => strtolower(trim($slug))]);
        return array_map(static function (array $row): array {
            $snapshot = json_decode((string)$row['snapshot'], true, 512, JSON_THROW_ON_ERROR);
            return [
                'revision' => (int)$row['revision'],
                'snapshot' => $snapshot,
                'changed_by' => (string)$row['changed_by'],
                'changed_at' => (string)$row['changed_at'],
            ];
        }, $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    private function hydrate(array $row): EditorialLens
    {
        return new EditorialLens(
            (string)$row['slug'],
            (string)$row['name'],
            (string)$row['description'],
            new GraphFilter(
                $this->parsePgArray((string)$row['relationship_types']),
                $this->parsePgArray((string)$row['relationship_statuses']),
                (float)$row['minimum_confidence'],
            ),
            (string)$row['status'],
            (int)($row['revision'] ?? 1),
            (string)($row['updated_by'] ?? 'system'),
        );
    }

    /** @return array<string,mixed> */
    private function snapshot(EditorialLens $lens): array
    {
        return [
            'slug' => $lens->slug,
            'name' => $lens->name,
            'description' => $lens->description,
            'filter' => $lens->filter->toArray(),
            'status' => $lens->status,
            'revision' => $lens->revision,
        ];
    }

    /** @param list<string> $values */
    private function pgArray(array $values): string
    {
        return '{' . implode(',', array_map(static fn(string $value): string => '"' . addcslashes($value, '"\\') . '"', $values)) . '}';
    }

    /** @return list<string> */
    private function parsePgArray(string $value): array
    {
        $value = trim($value, '{}');
        return $value === '' ? [] : array_map(static fn(string $item): string => trim($item, ' "'), str_getcsv($value));
    }
}
