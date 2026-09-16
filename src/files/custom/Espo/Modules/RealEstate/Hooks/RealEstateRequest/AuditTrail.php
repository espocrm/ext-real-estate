<?php
/*****************************************************
 * NGHIALAND DISPOSABLE — Slice A WI-3 (additive)
 * AuditTrail hook — writes NgRealEstateRequestAudit rows.
 * INSERT-only, immutable; does NOT modify upstream files.
 * Audit write failures never fail the business save.
 ****************************************************/

namespace Espo\Modules\RealEstate\Hooks\RealEstateRequest;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class AuditTrail
{
    /** @var array<string,string> field => semantic (V snapshot at create, A audit on change) */
    private const AUDITED = [
        'name' => 'V',
        'number' => 'V',
        'createdAt' => 'V',
        'assignedUserId' => 'A',
        'description' => 'A',
        'contactId' => 'A',
    ];

    /** linkMultiple fields to snapshot on change (A). */
    private const AUDITED_LINK_MULTIPLE = ['teams', 'contacts', 'locations'];

    public function __construct(private EntityManager $entityManager)
    {}

    public function afterSave(Entity $entity): void
    {
        try {
            $this->process($entity);
        } catch (\Throwable $e) {
            fwrite(\STDERR, 'AUDIT_TRAIL_WARN ' . $e->getMessage() . "\n");
        }
    }

    private function process(Entity $entity): void
    {
        $repo = $this->entityManager->getRDBRepository('NgRealEstateRequestAudit');
        $sequence = $repo->where(['realEstateRequestId' => $entity->getId()])->count() + 1;

        $rows = [];

        if ($entity->isNew()) {
            foreach (self::AUDITED as $field => $semantic) {
                if ($semantic !== 'V') {
                    continue;
                }
                $rows[] = $this->row($entity, $field, 'V', null, $this->stringify($entity->get($field)));
            }
        }

        foreach (self::AUDITED as $field => $semantic) {
            if ($semantic !== 'A') {
                continue;
            }
            if (!$entity->has($field) || $entity->get($field) == $entity->getFetched($field)) {
                continue;
            }
            $rows[] = $this->row(
                $entity, $field, 'A',
                $this->stringify($entity->getFetched($field)),
                $this->stringify($entity->get($field))
            );
        }

        foreach (self::AUDITED_LINK_MULTIPLE as $link) {
            if (!$entity->has($link . 'Ids')) {
                continue;
            }
            $before = $entity->getFetched($link . 'Ids');
            $after = $entity->get($link . 'Ids');
            if (json_encode($before) === json_encode($after)) {
                continue;
            }
            $rows[] = $this->row($entity, $link, 'A', $this->stringify($before), $this->stringify($after));
        }

        foreach ($rows as $row) {
            $row['sequence'] = $sequence;
            $audit = $repo->getNew();
            $audit->set($row);
            $repo->save($audit);
            $sequence++;
        }
    }

    private function row(Entity $entity, string $field, string $semantic, $before, $after): array
    {
        return [
            'realEstateRequestId' => $entity->getId(),
            'fieldName' => $field,
            'semantic' => $semantic,
            'beforeValue' => $before,
            'afterValue' => $after,
            'actorId' => $entity->get('modifiedById') ?: 'system',
            'occurredAt' => gmdate('Y-m-d H:i:s'),
        ];
    }

    private function stringify($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }
}
