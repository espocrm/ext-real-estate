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
class NgCommissionRevision extends Record
{
    public function getActionRead(Request $request, Response $response): stdClass
    {
        try { return parent::getActionRead($request, $response); }
        catch (Forbidden $e) { throw new NotFoundSilent(); }
    }
    public function postActionCreateRevision(Request $request)
    {
        $data=$request->getParsedBody();
        if(!$data || !is_object($data)) throw new BadRequest('Missing commission payload.');
        return $this->injectableFactory->create(CommissionRevisionCreator::class)->create(get_object_vars($data));
    }
}
