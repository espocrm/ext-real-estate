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

namespace Espo\Modules\RealEstate\Classes\Record\OutputFilter;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Record\Output\Filter;
use Espo\Entities\User;
use Espo\ORM\Entity;

/**
 * Gate B5 projection privacy.
 *
 * Strips SENS source/provider/terms/commission-like fields from NgOfferCycle
 * and NgSourceQuote output for non-data-admin actors. Data admins still see
 * the raw supply record; sales/manager get the approved projection only.
 * Server-side enforcement (Espo recordDefs outputFilter), not client hide.
 */
class SourceQuoteSupply implements Filter
{
    /** @var string[] */
    private const SENS_FIELDS = [
        'sourceProviderType',
        'sourceProviderId',
        'sourceSnapshot',
        'terms',
        'evidenceRefs',
        'sourceEvidenceRefs',
        'notes',
    ];

    public function __construct(
        private Acl $acl,
        private User $user
    ) {}

    public function filter(Entity $entity): void
    {
        $entityType = $entity->getEntityType();

        // Data admins (full read level = all) keep the raw supply record.
        if ($this->acl->getLevel($entityType, 'read') === Table::LEVEL_ALL || $this->user->isAdmin()) {
            return;
        }

        foreach (self::SENS_FIELDS as $field) {
            if ($entity->has($field)) {
                $entity->clear($field);
            }
        }
    }
}