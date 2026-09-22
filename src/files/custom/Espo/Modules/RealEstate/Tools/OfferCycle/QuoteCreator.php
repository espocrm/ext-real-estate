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

namespace Espo\Modules\RealEstate\Tools\OfferCycle;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use PDOException;
use RuntimeException;

/**
 * Serialized quote creation (Gate A guard, FC-SQ-012).
 *
 * The revision number is max(revisionNumber) + 1 over ALL rows for the cycle —
 * including soft-deleted ones, because the unique index
 * (offerCycleId, revisionNumber) keeps the slot of a deleted revision. Concurrent
 * creates can race on max+1; this service runs the allocate-and-insert inside a
 * transaction and retries on a duplicate-key/unique collision, so concurrent
 * writes serialize instead of surfacing a 500. This is the single write path for
 * new quotes.
 */
class QuoteCreator
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(private EntityManager $entityManager)
    {}

    public function create(array $values): Entity
    {
        $cycleId = $values['offerCycleId'] ?? null;

        if (!$cycleId) {
            throw new BadRequest('NgSourceQuote requires offerCycleId.');
        }

        if (empty($values['amount'])) {
            throw new BadRequest('NgSourceQuote requires amount.');
        }

        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                return $this->tryCreate($values);
            } catch (PDOException $e) {
                $lastError = $e;

                if (!$this->isDuplicateKey($e)) {
                    throw $e;
                }

                // Unique collision on (offerCycleId, revisionNumber): rollback
                // was already performed by the transaction manager, retry.
                usleep(50_000 * $attempt);
            } catch (RuntimeException $e) {
                throw $e;
            }
        }

        throw new Error(
            'NgSourceQuote revision allocation failed after ' . self::MAX_ATTEMPTS .
            ' attempts (concurrent create collision): ' . $lastError->getMessage()
        );
    }

    private function tryCreate(array $values): Entity
    {
        $tx = $this->entityManager->getTransactionManager();

        if ($tx->isStarted()) {
            throw new RuntimeException('Outer transaction is forbidden for quote create.');
        }

        $result = null;

        $tx->run(function () use ($values, &$result): void {
            // Serialize concurrent creators on the same cycle: lock the owning
            // NgOfferCycle row for update (always exists, unlike the head quote
            // which is absent for the first revision). This makes concurrent
            // creates queue on the same row instead of racing to max+1.
            $this->entityManager
                ->getRDBRepository('NgOfferCycle')
                ->forUpdate()
                ->where(['id' => $values['offerCycleId']])
                ->findOne();

            // FX1: allocate above the max over ALL rows, including soft-deleted
            // ones. The unique index (offerCycleId, revisionNumber) keeps the slot
            // of a deleted revision, so a max over live rows only re-assigns a
            // taken number and the insert fails with a duplicate-key error that
            // the retry loop cannot resolve (it recomputes the same number).
            $max = $this->entityManager
                ->getRDBRepository('NgSourceQuote')
                ->where(['offerCycleId' => $values['offerCycleId'], 'deleted' => [0, 1]])
                ->max('revisionNumber');

            $revision = $max === null ? 1 : (int) $max + 1;

            // FX1: the predecessor is the non-deleted head by revision order, not
            // the row that happens to hold the max (which may be deleted).
            $head = $this->entityManager
                ->getRDBRepository('NgSourceQuote')
                ->where(['offerCycleId' => $values['offerCycleId'], 'deleted' => false])
                ->order('revisionNumber', 'DESC')
                ->findOne();

            $entity = $this->entityManager->getNewEntity('NgSourceQuote');
            $entity->set($values);
            $entity->set('revisionNumber', $revision);

            if ($head) {
                $entity->set('predecessorId', $head->getId());
            }

            $this->entityManager->saveEntity($entity, [
                'silent' => true,
                'noStream' => true,
                'noNotifications' => true,
            ]);

            $result = $entity;
        });

        return $result;
    }

    private function isDuplicateKey(PDOException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, '1062') || str_contains($message, '23000');
    }
}