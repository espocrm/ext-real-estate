<?php
namespace Espo\Modules\RealEstate\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use stdClass;

class NgShortlistSnapshot extends Record
{
    /** Sent snapshots are immutable and can only be created by the bounded action. */
    public function postActionCreate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgShortlistSnapshot generic create is denied; use NgShortlistSnapshot/action/create.');
    }

    public function putActionUpdate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgShortlistSnapshot generic update is denied; snapshots are immutable.');
    }

    public function patchActionUpdate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgShortlistSnapshot generic update is denied; snapshots are immutable.');
    }

    public function deleteActionDelete(Request $request, Response $response): bool
    {
        throw new Forbidden('NgShortlistSnapshot generic delete is denied; snapshots are immutable.');
    }

    public function getActionRead(Request $request, Response $response): stdClass
    {
        try {
            $this->assertParentReadable($request);

            return parent::getActionRead($request, $response);
        }
        catch (Forbidden) { throw new NotFoundSilent(); }
    }

    /** A snapshot never widens access beyond its Shortlist and source quote. */
    private function assertParentReadable(Request $request): void
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new Forbidden('No ID.');
        }

        $record = $this->getRecordService()
            ->read($id, $this->readParamsFetcher->fetch($request));

        foreach ([
            'NgShortlist' => (string) $record->get('shortlistId'),
            'NgSourceQuote' => (string) $record->get('quoteId'),
        ] as $type => $parentId) {
            if ($parentId === '') {
                throw new Forbidden();
            }

            $parent = $this->recordServiceContainer
                ->get($type)
                ->getEntity($parentId);

            if (!$parent || !$this->acl->checkEntityRead($parent)) {
                throw new Forbidden();
            }
        }
    }
}