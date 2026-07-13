<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use Throwable;
use WonderOS\Knowledge\Graph\EditorialLens;
use WonderOS\Knowledge\Graph\EditorialLensRepository;

/** Exposes authenticated read models for the visual Editorial Lens Studio. */
final readonly class EditorialLensStudioReadApi
{
    public function __construct(
        private EditorialLensRepository $lenses,
        private string $editorialKey,
    ) {}

    /** @param array<string,mixed> $headers @return array{status:int,body:array<string,mixed>}|null */
    public function handle(string $method, string $path, array $headers = []): ?array
    {
        if ($path !== '/v1/editorial-lens-studio' && !preg_match('#^/v1/editorial-lens-studio/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $path, $matches)) {
            return null;
        }

        try {
            $this->authorise($headers);
            if ($method !== 'GET') {
                return $this->error(405, 'METHOD_NOT_ALLOWED', 'The Studio read endpoint supports GET only.');
            }

            if ($path === '/v1/editorial-lens-studio') {
                $items = array_map(fn(EditorialLens $lens): array => $this->serialise($lens), $this->lenses->all());
                return ['status' => 200, 'body' => ['success' => true, 'data' => $items, 'meta' => ['count' => count($items)], 'links' => (object)[]]];
            }

            return ['status' => 200, 'body' => $this->success($this->serialise($this->lenses->get($matches[1])))];
        } catch (DomainException $exception) {
            return $this->error(401, 'EDITORIAL_AUTHORISATION_FAILED', $exception->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'INTERNAL_ERROR', 'WonderOS could not load Editorial Lens Studio.');
        }
    }

    /** @param array<string,mixed> $headers */
    private function authorise(array $headers): void
    {
        $provided = (string)($headers['x-wonderos-editor-key'] ?? '');
        if ($this->editorialKey === '' || $provided === '' || !hash_equals($this->editorialKey, $provided)) {
            throw new DomainException('Editorial Lens Studio authorisation failed.');
        }
    }

    private function serialise(EditorialLens $lens): array
    {
        return [
            'slug' => $lens->slug,
            'name' => $lens->name,
            'description' => $lens->description,
            'types' => $lens->filter->types,
            'statuses' => $lens->filter->statuses,
            'minimum_confidence' => $lens->filter->minimumConfidence,
            'status' => $lens->status,
            'revision' => $lens->revision,
            'updated_by' => $lens->updatedBy,
        ];
    }

    private function success(array $data): array
    {
        return ['success' => true, 'data' => $data, 'meta' => (object)[], 'links' => (object)[]];
    }

    private function error(int $status, string $code, string $message): array
    {
        return ['status' => $status, 'body' => ['success' => false, 'error' => ['code' => $code, 'message' => $message]]];
    }
}
