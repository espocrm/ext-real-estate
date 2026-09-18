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

namespace Espo\Modules\RealEstate\Hooks\NgOfferCycle;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Server-side lifecycle guard for NgOfferCycle (Gate A4/A5).
 * - propertyId and intent are immutable after create.
 * - status follows a strict transition matrix.
 * - closeReason is required when entering a terminal state.
 * - terminal states (withdrawn/closed/expired) are not reopened automatically.
 * This hook never mutates RealEstateProperty status or price, and never
 * creates Deals/Opportunities. Closing a cycle is a cycle-scoped action only.
 */
class Lifecycle
{
    public static $order = 10;

    private const TRANSITIONS = [
        'draft' => ['pendingReview', 'paused', 'withdrawn', 'closed', 'expired'],
        'pendingReview' => ['active', 'paused', 'withdrawn', 'closed', 'expired'],
        'active' => ['paused', 'withdrawn', 'closed', 'expired'],
        'paused' => ['active', 'withdrawn', 'closed', 'expired'],
        'withdrawn' => [],
        'closed' => [],
        'expired' => [],
    ];

    private const TERMINAL = ['withdrawn', 'closed', 'expired'];

    public function __construct(private EntityManager $entityManager)
    {}

    public function beforeSave(Entity $entity, array $options): void
    {
        if ($entity->isNew()) {
            $this->assertInitial($entity);
            return;
        }

        // Immutable propertyId and intent on update.
        foreach (['propertyId', 'intent'] as $field) {
            if ($entity->has($field) && $entity->get($field) != $entity->getFetched($field)) {
                throw new BadRequest("NgOfferCycle.{$field} is immutable after create.");
            }
        }

        $this->assertTransition($entity);
        $this->assertCloseReason($entity);
    }

    private function assertInitial(Entity $entity): void
    {
        $status = (string) $entity->get('status');
        if ($status !== 'draft') {
            throw new BadRequest('NgOfferCycle must be created as draft.');
        }
        if (!$entity->get('propertyId')) {
            throw new BadRequest('NgOfferCycle requires propertyId.');
        }
    }

    private function assertTransition(Entity $entity): void
    {
        $from = (string) $entity->getFetched('status');
        $to = (string) $entity->get('status');
        if ($from === $to) {
            return;
        }
        if ($from === '') {
            return;
        }
        $allowed = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $allowed, true)) {
            throw new BadRequest("NgOfferCycle invalid transition {$from} -> {$to}.");
        }
    }

    private function assertCloseReason(Entity $entity): void
    {
        $to = (string) $entity->get('status');
        if (!in_array($to, self::TERMINAL, true)) {
            return;
        }
        // Require closeReason when moving into a terminal state (or already there on save).
        $hasReason = (string) $entity->get('closeReason') !== '';
        if (!$hasReason && $to === (string) $entity->getFetched('status')) {
            // Already terminal and saved before; allow edits that set a reason but do not force it retroactively.
            return;
        }
        if (!$hasReason) {
            throw new BadRequest('NgOfferCycle requires closeReason when closing/withdrawing/expiring.');
        }
    }
}