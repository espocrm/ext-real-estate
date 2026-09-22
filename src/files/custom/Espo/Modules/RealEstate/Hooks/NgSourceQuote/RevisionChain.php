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

    // H04: immutable fact inventory per contract — scenario/source/terms/asOf/
    // validity/policy facts are append-only; only freshness/verification/
    // eligibility/note lifecycle state remains mutable.
    private const IMMUTABLE_FIELDS = [
        'amount',
        'currency',
        'unit',
        'scenario',
        'offerCycleId',
        'sourceProviderType',
        'sourceProviderId',
        'sourceSnapshot',
        'receivedAt',
        'asOfAt',
        'validUntil',
        'terms',
        'policyVersion',
        'evidenceRefs',
        'enteredBy',
        'predecessorId',
    ];

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

        // FX1: the unique index (offerCycleId, revisionNumber) keeps the slot of a
        // soft-deleted row, so allocation must consider deleted rows too.
        // Excluding them re-assigns a taken number and the insert then fails with
        // a duplicate-key error that no retry can resolve. No row is renumbered,
        // no counter is reset and no history is deleted: this only reads a wider
        // max.
        $max = $this->entityManager
            ->getRDBRepository('NgSourceQuote')
            ->where(['offerCycleId' => $cycleId, 'deleted' => [0, 1]])
            ->max('revisionNumber');

        $revision = $max === null ? 1 : (int) $max + 1;
        $entity->set('revisionNumber', $revision);

        // FX1: the predecessor is the current non-deleted head, selected by
        // revision order — NOT by looking up revisionNumber === max, which would
        // silently link nothing (or link a deleted row) whenever the max belongs
        // to a soft-deleted revision.
        $head = $this->entityManager
            ->getRDBRepository('NgSourceQuote')
            ->where(['offerCycleId' => $cycleId, 'deleted' => false])
            ->order('revisionNumber', 'DESC')
            ->findOne();

        if ($head) {
            $entity->set('predecessorId', $head->getId());
        }
    }
}