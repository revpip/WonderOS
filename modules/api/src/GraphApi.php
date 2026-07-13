<?php

declare(strict_types=1);

namespace WonderOS\Api;

use DomainException;
use InvalidArgumentException;
use Throwable;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Graph\GraphFilter;
use WonderOS\Knowledge\Graph\GraphTraversal;

/** Exposes bounded graph traversal without mixing traversal rules into HTTP routing. */
final readonly class GraphApi
{
    public function __construct(private GraphTraversal $graph)
    {
    }

    /** @param array<string,mixed> $query */
    /** @return array{status:int,body:array<string,mixed>}|null */
    public function handle(string $method, string $path, array $query = []): ?array
    {
        if (!preg_match('#^/v1/entities/(WND-[A-Z]{3}-\d{6})/graph$#i', $path, $matches)) {
            return null;
        }

        try {
            if ($method !== 'GET') {
                return $this->error(405, 'METHOD_NOT_ALLOWED', 'The graph endpoint supports GET only.');
            }

            $depth = isset($query['depth']) ? filter_var($query['depth'], FILTER_VALIDATE_INT) : 1;
            $maxNodes = isset($query['max_nodes']) ? filter_var($query['max_nodes'], FILTER_VALIDATE_INT) : 100;
            $minimumConfidence = isset($query['min_confidence'])
                ? filter_var($query['min_confidence'], FILTER_VALIDATE_FLOAT)
                : 0.0;

            if ($depth === false || $maxNodes === false || $minimumConfidence === false) {
                throw new InvalidArgumentException('depth and max_nodes must be integers; min_confidence must be numeric.');
            }

            $filter = new GraphFilter(
                $this->csv($query['types'] ?? null),
                $this->csv($query['statuses'] ?? null),
                (float) $minimumConfidence,
            );

            $data = $this->graph->traverse(
                WonderId::parse($matches[1]),
                $depth,
                $maxNodes,
                $filter,
            );

            return [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => $data,
                    'meta' => [
                        'node_count' => count($data['nodes']),
                        'edge_count' => count($data['edges']),
                        'filtered' => $filter->types !== []
                            || $filter->statuses !== []
                            || $filter->minimumConfidence > 0.0,
                    ],
                    'links' => (object) [],
                ],
            ];
        } catch (EntityNotFound $exception) {
            return $this->error(404, 'ENTITY_NOT_FOUND', $exception->getMessage());
        } catch (InvalidArgumentException|DomainException $exception) {
            return $this->error(422, 'VALIDATION_FAILED', $exception->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'INTERNAL_ERROR', 'WonderOS could not traverse this graph.');
        }
    }

    /** @return list<string> */
    private function csv(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_string($value)) {
            throw new InvalidArgumentException('Graph filters must be comma-separated strings.');
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $item): string => strtolower(trim($item)),
            explode(',', $value),
        ), static fn (string $item): bool => $item !== '')));
    }

    /** @return array{status:int,body:array<string,mixed>} */
    private function error(int $status, string $code, string $message): array
    {
        return [
            'status' => $status,
            'body' => ['success' => false, 'error' => ['code' => $code, 'message' => $message]],
        ];
    }
}
