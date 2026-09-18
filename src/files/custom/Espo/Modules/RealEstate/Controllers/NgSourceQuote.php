<?php
/************************************************************************
* This file is part of EspoCRM.
*
* EspoCRM – Open Source CRM application.
* Copyright (C) 2014-2026 EspoCRM, Inc.
* Website: https://www.espocrm.com
*
* GNU AGPLv3 header preserved (see upstream modules).
************************************************************************/

namespace Espo\Modules\RealEstate\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Api\Response;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFoundSilent;
use stdClass;
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\RealEstate\Tools\OfferCycle\QuoteCreator;

class NgSourceQuote extends Record
{
    /** Normalize foreign-existing and unknown IDs to one silent 404 shape. */
    public function getActionRead(Request $request, Response $response): stdClass
    {
        try {
            return parent::getActionRead($request, $response);
        } catch (Forbidden $e) {
            throw new NotFoundSilent();
        }
    }

    /**
     * Serialized revision create (Gate A guard): allocates revisionNumber in a
     * transaction and retries on unique collision. Generic create remains the
     * Record default and is not retried.
     *
     * @throws BadRequest
     */
    public function postActionCreateRevision(Request $request)
    {
        $data = $request->getParsedBody();

        if (!$data || !is_object($data)) {
            throw new BadRequest('Missing quote payload.');
        }

        $values = get_object_vars($data);

        $entity = $this->injectableFactory
            ->create(QuoteCreator::class)
            ->create($values);

        return $entity;
    }
}
