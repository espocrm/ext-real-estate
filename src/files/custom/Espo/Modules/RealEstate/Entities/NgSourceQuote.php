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

class NgSourceQuote extends Entity
{
    public const ENTITY_TYPE = 'NgSourceQuote';

    public function isCurrent(): bool
    {
        return (string) $this->get('freshnessState') === 'current';
    }
}