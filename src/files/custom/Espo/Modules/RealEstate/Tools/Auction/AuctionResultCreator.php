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

namespace Espo\Modules\RealEstate\Tools\Auction;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use PDOException;
use RuntimeException;

/**
 * Serialized result creation (Gate A guard, FC-SQ-012).
 *
 * The revision number is current-head + 1, guarded by the DB unique index
 * (lotId, revisionNumber). Concurrent creates can race on max+1; this
 * service runs the allocate-and-insert inside a transaction and retries on a
 * duplicate-key/unique collision, so concurrent writes serialize instead of
 * surfacing a 500. This is the single write path for new results.
 */
class AuctionResultCreator
{
    private const MAX_ATTEMPTS = 3;

    public function __construct(private EntityManager $entityManager)
    {}

    public function create(array $values): Entity
    {
        $lotId = $values['lotId'] ?? null;

        if (!$lotId) {
            throw new BadRequest('NgAuctionResult requires lotId.');
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

                // Unique collision on (lotId, revisionNumber): rollback
                // was already performed by the transaction manager, retry.
                usleep(50_000 * $attempt);
            } catch (RuntimeException $e) {
                throw $e;
            }
        }

        throw new Error(
            'NgAuctionResult revision allocation failed after ' . self::MAX_ATTEMPTS .
            ' attempts (concurrent create collision): ' . $lastError->getMessage()
        );
    }

    private function tryCreate(array $values): Entity
    {
        $tx = $this->entityManager->getTransactionManager();

        if ($tx->isStarted()) {
            throw new RuntimeException('Outer transaction is forbidden for result create.');
        }

        $result = null;

        $tx->run(function () use ($values, &$result): void {
            // Serialize concurrent creators on the same cycle: lock the owning
            // NgAuctionLot row for update (always exists, unlike the head result
            // which is absent for the first revision). This makes concurrent
            // creates queue on the same row instead of racing to max+1.
            $this->entityManager
                ->getRDBRepository('NgAuctionLot')
                ->forUpdate()
                ->where(['id' => $values['lotId']])
                ->findOne();

            $head = $this->entityManager
                ->getRDBRepository('NgAuctionResult')
                ->where(['lotId' => $values['lotId']])
                ->order('revisionNumber', 'DESC')
                ->findOne();

            $revision = $head ? (int) $head->get('revisionNumber') + 1 : 1;

            $entity = $this->entityManager->getNewEntity('NgAuctionResult');
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