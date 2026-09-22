<?php
namespace Espo\Modules\RealEstate\Controllers;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\Modules\RealEstate\Tools\Commission\CommissionRevisionCreator;
use stdClass;

/**
 * H02/H03/H05 — NgCommissionRevision generic write surface is closed.
 * Revisions are appended only through the bounded createRevision action so
 * the hardened creator (ACL, parent check, input allowlist, decimal
 * validation, server-derived expectedAmount) cannot be bypassed by the
 * inherited generic Record create/update/delete routes.
 */
class NgCommissionRevision extends Record
{
    public function getActionRead(Request $request, Response $response): stdClass
    {
        try { return parent::getActionRead($request, $response); }
        catch (Forbidden $e) { throw new NotFoundSilent(); }
    }

    public function postActionCreate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgCommissionRevision generic create is denied; use NgCommissionRevision/action/createRevision.');
    }

    public function putActionUpdate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgCommissionRevision generic update is denied; append a correction revision.');
    }

    public function patchActionUpdate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgCommissionRevision generic update is denied; append a correction revision.');
    }

    public function deleteActionDelete(Request $request, Response $response): bool
    {
        throw new Forbidden('NgCommissionRevision generic delete is denied; history is immutable.');
    }

    public function postActionCreateRevision(Request $request): stdClass
    {
        $data=$request->getParsedBody();
        if(!$data || !is_object($data)) throw new BadRequest('Missing commission payload.');
        $creator=$this->injectableFactory->create(CommissionRevisionCreator::class);
        $entity=$creator->create(get_object_vars($data));
        return (object) [
            'id' => (string) $entity->getId(),
            'offerCycleId' => $entity->get('offerCycleId') ? (string) $entity->get('offerCycleId') : null,
            'sourceQuoteId' => $entity->get('sourceQuoteId') ? (string) $entity->get('sourceQuoteId') : null,
            'revisionNumber' => (int) $entity->get('revisionNumber'),
            'expectedAmount' => (string) $entity->get('expectedAmount'),
            // Idempotency is observable so a retry can be distinguished from a
            // fresh append, and so the collision-replay path is testable.
            'replayed' => $creator->wasReplayed(),
            'replayedOnCollision' => $creator->wasReplayedOnCollision(),
        ];
    }
}
