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
use Espo\ORM\Entity;

class NgSourceQuote extends Record
{
    /**
     * FX3: fields the create receipt may echo back. Fixed whitelist, so the
     * receipt can never carry a field the caller could not otherwise read — in
     * particular no SourceQuoteSupply SENS field (sourceProviderType,
     * sourceProviderId, sourceSnapshot, terms, evidenceRefs, notes). The
     * receipt test asserts the whitelist stays disjoint from that list.
     */
    private const RECEIPT_FIELDS = ['id', 'offerCycleId', 'revisionNumber', 'predecessorId'];

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
    public function postActionCreateRevision(Request $request): stdClass
    {
        $data = $request->getParsedBody();

        if (!$data || !is_object($data)) {
            throw new BadRequest('Missing quote payload.');
        }

        $values = get_object_vars($data);

        $entity = $this->injectableFactory
            ->create(QuoteCreator::class)
            ->create($values);

        return $this->buildCreateReceipt($entity);
    }

    /**
     * FX3: a custom action must return a serializable projection. Returning the
     * entity itself produced an empty 200 body, so a caller could not read back
     * what it had just created.
     */
    private function buildCreateReceipt(Entity $entity): stdClass
    {
        $receipt = new stdClass();

        foreach (self::RECEIPT_FIELDS as $field) {
            if ($field === 'id') {
                $receipt->$field = (string) $entity->getId();

                continue;
            }

            $value = $entity->has($field) ? $entity->get($field) : null;

            if ($value === null || $value === '') {
                $receipt->$field = null;

                continue;
            }

            $receipt->$field = $field === 'revisionNumber' ? (int) $value : (string) $value;
        }

        return $receipt;
    }
}
