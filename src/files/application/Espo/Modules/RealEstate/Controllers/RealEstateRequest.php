<?php

namespace Espo\Modules\RealEstate\Controllers;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\NotFound;

class RealEstateRequest extends \Espo\Core\Templates\Controllers\Base
{
    public function postActionSetNotInterested($params, $data, $request)
    {
        if (is_array($data)) $data = (object) $data;

        if (empty($data->requestId) || empty($data->propertyId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->setNotIntereseted($data->requestId, $data->propertyId);
    }

    public function postActionUnsetNotInterested($params, $data, $request)
    {
        if (is_array($data)) $data = (object) $data;

        if (empty($data->requestId) || empty($data->propertyId)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->unsetNotIntereseted($data->requestId, $data->propertyId);
    }
}
