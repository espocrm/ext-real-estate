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

namespace Espo\Modules\RealEstate\Entities;

use Espo\Core\ORM\Entity;

class NgOfferCycle extends Entity
{
    public const ENTITY_TYPE = 'NgOfferCycle';

    public const TERMINAL_STATUSES = ['withdrawn', 'closed', 'expired'];

    public function isTerminal(): bool
    {
        return in_array((string) $this->get('status'), self::TERMINAL_STATUSES, true);
    }
}