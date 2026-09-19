<?php
namespace Espo\Modules\RealEstate\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use Espo\Modules\RealEstate\Tools\Shortlist\StatusUpdater;
use stdClass;

class NgShortlist extends Record
{
    /**
     * Deny-by-default generic writes. NgShortlist rows are created and
     * transitioned only through bounded action routes (NgShortlist/action/create,
     * NgShortlist/:id/updateStatus), so the generic Record create/update/delete
     * surface stays closed even for actors that carry ACL levels on the scope.
     */
    public function postActionCreate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgShortlist generic create is denied; use NgShortlist/action/create.');
    }

    public function putActionUpdate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgShortlist generic update is denied; use NgShortlist/:id/updateStatus.');
    }

    public function patchActionUpdate(Request $request, Response $response): stdClass
    {
        throw new Forbidden('NgShortlist generic update is denied; use NgShortlist/:id/updateStatus.');
    }

    public function deleteActionDelete(Request $request, Response $response): bool
    {
        throw new Forbidden('NgShortlist generic delete is denied.');
    }

    /**
     * Bounded status transition (FC-SL-004/006/008). Accepts only status + reason,
     * validates the transition server-side, records event facts, and never touches
     * Inquiry/Property/Deal state.
     */
    public function postActionUpdateStatus(Request $request): stdClass
    {
        $data = $request->getParsedBody();

        if (!$data || !is_object($data)) {
            throw new BadRequest('Missing transition payload.');
        }

        $id = isset($data->id) ? (string) $data->id : '';

        if ($id === '') {
            throw new BadRequest('id is required.');
        }

        return (object) $this->injectableFactory
            ->create(StatusUpdater::class)
            ->update($id, $data);
    }

    public function getActionRead(Request $request, Response $response): stdClass
    {
        try {
            $this->assertParentReadable($request);

            return parent::getActionRead($request, $response);
        }
        catch (Forbidden $e) { throw new NotFoundSilent(); }
    }

    /** Parent-read gate (FC-SL-001/002): the shortlist is only readable when
     * its Inquiry and Property parents are readable by the actor. */
    private function assertParentReadable(Request $request): void
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new Forbidden('No ID.');
        }

        $record = $this->getRecordService()
            ->read($id, $this->readParamsFetcher->fetch($request));

        $inquiryId = $record->get('inquiryId');
        $propertyId = $record->get('propertyId');

        if (!(string) $inquiryId || !(string) $propertyId) {
            throw new Forbidden();
        }

        $parents = [
            'RealEstateRequest' => (string) $inquiryId,
            'RealEstateProperty' => (string) $propertyId,
        ];

        foreach ($parents as $type => $parentId) {
            if ($parentId === '') {
                continue;
            }

            $parent = $this->recordServiceContainer
                ->get($type)
                ->getEntity($parentId);

            if (!$parent) {
                throw new Forbidden();
            }

            if (!$this->acl->checkEntityRead($parent)) {
                throw new Forbidden();
            }
        }
    }
}