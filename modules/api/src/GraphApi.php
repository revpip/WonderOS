<?php

declare(strict_types=1);

namespace WonderOS\Api;

use DomainException;
use InvalidArgumentException;
use Throwable;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Graph\EditorialLens;
use WonderOS\Knowledge\Graph\EditorialLensRepository;
use WonderOS\Knowledge\Graph\GraphFilter;
use WonderOS\Knowledge\Graph\GraphTraversal;

/** Exposes bounded graph traversal and reusable editorial lenses. */
final readonly class GraphApi
{
    public function __construct(
        private GraphTraversal $graph,
        private ?EditorialLensRepository $lenses = null,
    ) {}

    /** @param array<string,mixed> $query */
    public function handle(string $method, string $path, array $query = []): ?array
    {
        if ($path === '/v1/editorial-lenses') {
            if ($method !== 'GET') return $this->error(405,'METHOD_NOT_ALLOWED','The editorial lenses endpoint supports GET only.');
            try { return $this->listLenses(); } catch (Throwable) { return $this->error(500,'INTERNAL_ERROR','WonderOS could not list editorial lenses.'); }
        }
        if (!preg_match('#^/v1/entities/(WND-[A-Z]{3}-\d{6})/graph$#i', $path, $matches)) return null;

        try {
            if ($method !== 'GET') return $this->error(405,'METHOD_NOT_ALLOWED','The graph endpoint supports GET only.');
            $depth = isset($query['depth']) ? filter_var($query['depth'], FILTER_VALIDATE_INT) : 1;
            $maxNodes = isset($query['max_nodes']) ? filter_var($query['max_nodes'], FILTER_VALIDATE_INT) : 100;
            if ($depth === false || $maxNodes === false) throw new InvalidArgumentException('depth and max_nodes must be integers.');

            $lens = $this->resolveLens($query['lens'] ?? null);
            $base = $lens?->filter ?? new GraphFilter();
            $minimumConfidence = array_key_exists('min_confidence',$query)
                ? filter_var($query['min_confidence'], FILTER_VALIDATE_FLOAT)
                : $base->minimumConfidence;
            if ($minimumConfidence === false) throw new InvalidArgumentException('min_confidence must be numeric.');

            $filter = new GraphFilter(
                array_key_exists('types',$query) ? $this->csv($query['types']) : $base->types,
                array_key_exists('statuses',$query) ? $this->csv($query['statuses']) : $base->statuses,
                (float)$minimumConfidence,
            );
            $data = $this->graph->traverse(WonderId::parse($matches[1]),$depth,$maxNodes,$filter);
            $data['lens'] = $lens === null ? null : $this->serializeLens($lens);

            return ['status'=>200,'body'=>['success'=>true,'data'=>$data,'meta'=>[
                'node_count'=>count($data['nodes']),
                'edge_count'=>count($data['edges']),
                'filtered'=>$lens !== null || $filter->types !== [] || $filter->statuses !== [] || $filter->minimumConfidence > 0.0,
                'lens'=>$lens?->slug,
            ],'links'=>(object)[]]];
        } catch (EntityNotFound $exception) {
            return $this->error(404,'ENTITY_NOT_FOUND',$exception->getMessage());
        } catch (InvalidArgumentException|DomainException $exception) {
            return $this->error(422,'VALIDATION_FAILED',$exception->getMessage());
        } catch (Throwable) {
            return $this->error(500,'INTERNAL_ERROR','WonderOS could not traverse this graph.');
        }
    }

    private function resolveLens(mixed $value): ?EditorialLens
    {
        if ($value === null || $value === '') return null;
        if (!is_string($value)) throw new InvalidArgumentException('lens must be a string.');
        $repository = $this->lenses ?? throw new DomainException('Editorial lens storage is unavailable.');
        $lens = $repository->get(strtolower(trim($value)));
        if ($lens->status !== 'active') throw new DomainException(sprintf('Editorial lens "%s" is deprecated.',$lens->slug));
        return $lens;
    }

    private function listLenses(): array
    {
        $repository = $this->lenses ?? throw new DomainException('Editorial lens storage is unavailable.');
        $items = array_map(fn(EditorialLens $lens): array => $this->serializeLens($lens),$repository->active());
        return ['status'=>200,'body'=>['success'=>true,'data'=>$items,'meta'=>['count'=>count($items)],'links'=>(object)[]]];
    }

    private function serializeLens(EditorialLens $lens): array
    {
        return ['slug'=>$lens->slug,'name'=>$lens->name,'description'=>$lens->description,'filters'=>$lens->filter->toArray(),'status'=>$lens->status];
    }

    /** @return list<string> */
    private function csv(mixed $value): array
    {
        if ($value === null || $value === '') return [];
        if (!is_string($value)) throw new InvalidArgumentException('Graph filters must be comma-separated strings.');
        return array_values(array_unique(array_filter(array_map(static fn(string $item): string => strtolower(trim($item)),explode(',',$value)),static fn(string $item): bool => $item !== '')));
    }

    private function error(int $status,string $code,string $message): array
    {
        return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]];
    }
}
