<?php

declare(strict_types=1);
namespace WonderOS\Api;

use DomainException;
use InvalidArgumentException;
use JsonException;
use Throwable;
use WonderOS\Core\Auth\AuthService;
use WonderOS\Core\Auth\User;
use WonderOS\Core\Identity\WonderId;
use WonderOS\Knowledge\Entity\EntityNotFound;
use WonderOS\Knowledge\Graph\EditorialLens;
use WonderOS\Knowledge\Graph\EditorialLensRepository;
use WonderOS\Knowledge\Graph\GraphFilter;
use WonderOS\Knowledge\Graph\GraphTraversal;

/** Provides authenticated, role-governed administration of editorial lenses. */
final readonly class EditorialLensStudioApi
{
    public function __construct(
        private EditorialLensRepository $lenses,
        private GraphTraversal $graph,
        private AuthService $auth,
    ) {}

    /** @param array<string,mixed> $headers @return array{status:int,body:array<string,mixed>}|null */
    public function handle(string $method, string $path, string $rawBody = '', array $headers = []): ?array
    {
        if ($path !== '/v1/editorial-lens-studio'
            && $path !== '/v1/editorial-lenses'
            && !preg_match('#^/v1/editorial-lenses/[a-z0-9]+(?:-[a-z0-9]+)*(?:/(?:activate|deprecate|preview|revisions))?$#', $path)) {
            return null;
        }

        try {
            $user = $this->auth->authenticate($headers['authorization'] ?? null);
            $user->role->assertPermits('editorial.manage');
            $editor = $user->email;

            if ($method === 'GET' && $path === '/v1/editorial-lens-studio') {
                return ['status'=>200,'body'=>$this->success(array_map(fn(EditorialLens $lens): array => $this->serialise($lens), $this->lenses->all()))];
            }
            if ($method === 'POST' && $path === '/v1/editorial-lenses') {
                return $this->create($this->decode($rawBody), $editor);
            }

            preg_match('#^/v1/editorial-lenses/([a-z0-9]+(?:-[a-z0-9]+)*)(?:/(activate|deprecate|preview|revisions))?$#', $path, $matches);
            $slug = $matches[1] ?? '';
            $action = $matches[2] ?? null;

            return match (true) {
                $method === 'PUT' && $action === null => $this->revise($slug, $this->decode($rawBody), $editor),
                $method === 'POST' && $action === 'activate' => $this->activate($slug, $this->decode($rawBody), $editor),
                $method === 'POST' && $action === 'deprecate' => $this->deprecate($slug, $this->decode($rawBody), $editor),
                $method === 'POST' && $action === 'preview' => $this->preview($slug, $this->decode($rawBody)),
                $method === 'GET' && $action === 'revisions' => $this->revisions($slug),
                default => $this->error(405, 'METHOD_NOT_ALLOWED', 'The Editorial Lens Studio method is not supported.'),
            };
        } catch (JsonException|InvalidArgumentException $exception) {
            return $this->error(422, 'VALIDATION_FAILED', $exception->getMessage());
        } catch (EntityNotFound $exception) {
            return $this->error(404, 'ENTITY_NOT_FOUND', $exception->getMessage());
        } catch (DomainException $exception) {
            $message = strtolower($exception->getMessage());
            $authentication = str_contains($message, 'session') || str_contains($message, 'bearer') || str_contains($message, 'suspended');
            return $this->error($authentication ? 401 : 403, $authentication ? 'AUTHENTICATION_FAILED' : 'AUTHORISATION_FAILED', $exception->getMessage());
        } catch (Throwable) {
            return $this->error(500, 'INTERNAL_ERROR', 'WonderOS could not complete the Editorial Lens Studio request.');
        }
    }

    private function create(array $payload, string $editor): array
    {
        foreach (['slug','name','description'] as $required) $this->requiredString($payload, $required);
        $lens = EditorialLens::create(trim($payload['slug']), trim($payload['name']), trim($payload['description']), $this->filter($payload), $editor);
        $this->lenses->save($lens);
        return ['status'=>201,'body'=>$this->success($this->serialise($lens))];
    }

    private function revise(string $slug, array $payload, string $editor): array
    {
        foreach (['name','description','expected_revision'] as $required) if (!array_key_exists($required,$payload)) throw new InvalidArgumentException("$required is required.");
        $current=$this->lenses->get($slug);
        $lens=$current->revise((string)$payload['name'],(string)$payload['description'],$this->filter($payload),$this->integer($payload['expected_revision'],'expected_revision'),$editor);
        $this->lenses->save($lens,$current->revision);
        return ['status'=>200,'body'=>$this->success($this->serialise($lens))];
    }

    private function activate(string $slug,array $payload,string $editor): array
    {
        $current=$this->lenses->get($slug); $lens=$current->activate($this->expectedRevision($payload),$editor); $this->lenses->save($lens,$current->revision);
        return ['status'=>200,'body'=>$this->success($this->serialise($lens))];
    }

    private function deprecate(string $slug,array $payload,string $editor): array
    {
        $current=$this->lenses->get($slug); $lens=$current->deprecate($this->expectedRevision($payload),$editor); $this->lenses->save($lens,$current->revision);
        return ['status'=>200,'body'=>$this->success($this->serialise($lens))];
    }

    private function preview(string $slug,array $payload): array
    {
        $lens=$this->lenses->get($slug); $this->requiredString($payload,'root_wonder_id');
        $graph=$this->graph->traverse(WonderId::parse($payload['root_wonder_id']),isset($payload['depth'])?$this->integer($payload['depth'],'depth'):2,isset($payload['max_nodes'])?$this->integer($payload['max_nodes'],'max_nodes'):100,$lens->filter);
        return ['status'=>200,'body'=>$this->success(['lens'=>$this->serialise($lens),'preview'=>$graph])];
    }

    private function revisions(string $slug): array
    {
        $this->lenses->get($slug); $history=$this->lenses->history($slug);
        return ['status'=>200,'body'=>['success'=>true,'data'=>$history,'meta'=>['count'=>count($history),'lens_slug'=>$slug],'links'=>(object)[]]];
    }

    private function filter(array $payload): GraphFilter
    {
        $types=$payload['types']??[]; $statuses=$payload['statuses']??[];
        if(!is_array($types)||!is_array($statuses)) throw new InvalidArgumentException('types and statuses must be arrays.');
        return new GraphFilter(array_values(array_unique($types)),array_values(array_unique($statuses)),isset($payload['minimum_confidence'])?(float)$payload['minimum_confidence']:0.0);
    }

    private function expectedRevision(array $payload): int { if(!array_key_exists('expected_revision',$payload)) throw new InvalidArgumentException('expected_revision is required.'); return $this->integer($payload['expected_revision'],'expected_revision'); }
    private function requiredString(array $payload,string $field): void { if(!isset($payload[$field])||!is_string($payload[$field])||trim($payload[$field])==='') throw new InvalidArgumentException("$field is required."); }
    private function integer(mixed $value,string $field): int { $parsed=filter_var($value,FILTER_VALIDATE_INT); if($parsed===false) throw new InvalidArgumentException("$field must be an integer."); return $parsed; }
    private function decode(string $rawBody): array { $decoded=json_decode($rawBody,true,512,JSON_THROW_ON_ERROR); if(!is_array($decoded)) throw new InvalidArgumentException('Request body must be a JSON object.'); return $decoded; }
    private function serialise(EditorialLens $lens): array { return ['slug'=>$lens->slug,'name'=>$lens->name,'description'=>$lens->description,'types'=>$lens->filter->types,'statuses'=>$lens->filter->statuses,'minimum_confidence'=>$lens->filter->minimumConfidence,'status'=>$lens->status,'revision'=>$lens->revision,'updated_by'=>$lens->updatedBy]; }
    private function success(array $data): array { return ['success'=>true,'data'=>$data,'meta'=>(object)[],'links'=>(object)[]]; }
    private function error(int $status,string $code,string $message): array { return ['status'=>$status,'body'=>['success'=>false,'error'=>['code'=>$code,'message'=>$message]]]; }
}
