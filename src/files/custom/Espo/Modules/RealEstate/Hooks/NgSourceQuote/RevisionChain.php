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

namespace Espo\Modules\RealEstate\Hooks\NgSourceQuote;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Immutable revision chain for NgSourceQuote (Gate A6, FC-SQ-012).
 * - On create: assign the next revisionNumber for the cycle and link the
 *   current head as predecessor. Never overwrites an earlier quote.
 * - On update: core quote facts (amount/currency/unit/offerCycleId) are
 *   immutable; only freshness/verification/eligibility state is mutable.
 * - A new source fact must be created as a new quote, not by editing an old one.
 */
class RevisionChain
{
    public static $order = 20;

    private const IMMUTABLE_FIELDS = ['amount', 'currency', 'unit', 'offerCycleId'];

    public function __construct(private EntityManager $entityManager)
    {}

    public function beforeSave(Entity $entity, array $options): void
    {
        if ($entity->isNew()) {
            $this->assignRevision($entity);
            return;
        }

        foreach (self::IMMUTABLE_FIELDS as $field) {
            if ($entity->has($field) && $entity->get($field) != $entity->getFetched($field)) {
                throw new BadRequest("NgSourceQuote.{$field} is immutable; create a new quote revision instead.");
            }
        }
    }

    private function assignRevision(Entity $entity): void
    {
        $cycleId = $entity->get('offerCycleId');
        if (!$cycleId) {
            throw new BadRequest('NgSourceQuote requires offerCycleId.');
        }
        if (!$entity->get('amount')) {
            throw new BadRequest('NgSourceQuote requires amount.');
        }

        // Note: metadata default is 1 for revisionNumber, so a guard based on
        // get('revisionNumber') would incorrectly skip assignment. The hook
        // always allocates max+1 inside the caller's transaction; QuoteCreator
        // provides the row-lock and duplicate retry for the serialized path,
        // while generic create is still guarded by the DB unique index.

        $max = $this->entityManager
            ->getRDBRepository('NgSourceQuote')
            ->where(['offerCycleId' => $cycleId])
            ->max('revisionNumber');

        $revision = $max === null ? 1 : (int) $max + 1;
        $entity->set('revisionNumber', $revision);

        $head = $this->entityManager
            ->getRDBRepository('NgSourceQuote')
            ->where(['offerCycleId' => $cycleId, 'revisionNumber' => (int) $max])
            ->findOne();

        if ($head) {
            $entity->set('predecessorId', $head->getId());
        }
    }
}