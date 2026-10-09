<?php
namespace Espo\Modules\RealEstate\Controllers;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use stdClass;
class NgAuctionSession extends Record
{
    public function getActionRead(Request $request, Response $response): stdClass
    {
        try { return parent::getActionRead($request, $response); }
        catch (Forbidden $e) { throw new NotFoundSilent(); }
    }
}
