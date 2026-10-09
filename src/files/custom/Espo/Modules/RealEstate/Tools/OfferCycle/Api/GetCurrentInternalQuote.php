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

namespace Espo\Modules\RealEstate\Tools\OfferCycle\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\RealEstate\Entities\NgOfferCycle;
use Espo\Modules\RealEstate\Tools\OfferCycle\Selector;
use Espo\ORM\EntityManager;

/**
 * @noinspection PhpUnused
 */
class GetCurrentInternalQuote implements Action
{
    public function __construct(
        private Selector $selector,
        private Acl $acl,
        private EntityManager $entityManager
    ) {}

    public function process(Request $request): Response
    {
        $id = $request->getRouteParam('id');

        if (!$id) {
            throw new BadRequest();
        }

        $cycle = $this->entityManager->getEntityById(NgOfferCycle::ENTITY_TYPE, $id);

        if (!$cycle) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($cycle)) {
            throw new Forbidden();
        }

        $asOf = $request->getQueryParam('asOf') ?: null;
        $policyVersion = $request->getQueryParam('policyVersion') ?: null;

        return ResponseComposer::json(
            $this->selector->selectInternalBest((string) $id, $asOf, $policyVersion)
        );
    }
}