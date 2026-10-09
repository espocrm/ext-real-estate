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
use Espo\Core\Exceptions\NotFound;
use Espo\ORM\EntityManager;

/**
 * Current internal-best quote selector (Gate B, FG-14 / GAP12-08).
 *
 * Derived, read-only computation. It never writes NgSourceQuote,
 * NgOfferCycle, or native RealEstateProperty price/status.
 *
 * Definition:
 *  - comparable = same currency + unit + scenario as the cycle intent basis;
 *  - eligible   = freshnessState=current AND verificationState=accepted AND
 *                 freshnessState not in {withdrawn, expired};
 *  - best       = lowest amount; tie-break verifiedAt DESC, then id ASC;
 *  - no eligible quote -> status NEEDS_PRICE ("CHỜ GIÁ/CẦN XÁC MINH"),
 *    never 0 and never a stale fallback.
 */
class Selector
{
    public const RULE_VERSION = 's055-selector-v1';

    public const STATUS_OK = 'OK';
    public const STATUS_NEEDS_PRICE = 'NEEDS_PRICE';

    public function __construct(private EntityManager $entityManager)
    {}

    /**
     * @return array{
     *   status: string,
     *   ruleVersion: string,
     *   asOf: ?string,
     *   cycleId: string,
     *   intent: ?string,
     *   comparableKey: array<string, ?string>,
     *   candidates: string[],
     *   excluded: array<string, string>,
     *   chosenQuoteId: ?string,
     *   chosenAmount: ?string,
     *   chosenCurrency: ?string,
     *   reason: string
     * }
     */
    public function selectInternalBest(string $cycleId, ?string $asOf = null, ?string $policyVersion = null): array
    {
        if ($cycleId === '') {
            throw new BadRequest('cycleId is required.');
        }

        $cycle = $this->entityManager->getEntityById('NgOfferCycle', $cycleId);

        if (!$cycle) {
            throw new NotFound('NgOfferCycle not found.');
        }

        $asOf = $asOf ?? gmdate('Y-m-d H:i:s');

        $quotes = $this->entityManager
            ->getRDBRepository('NgSourceQuote')
            ->where(['offerCycleId' => $cycleId, 'deleted' => false])
            ->order('revisionNumber')
            ->find();

        $eligible = [];
        $excluded = [];

        foreach ($quotes as $quote) {
            $id = (string) $quote->getId();
            $freshness = (string) $quote->get('freshnessState');
            $verification = (string) $quote->get('verificationState');
            $validUntil = $quote->get('validUntil');

            if (in_array($freshness, ['withdrawn', 'expired'], true)) {
                $excluded[$id] = 'freshness:' . $freshness;
                continue;
            }

            if ($freshness !== 'current') {
                $excluded[$id] = 'freshness:' . $freshness;
                continue;
            }

            if ($validUntil !== null && $validUntil !== '' && $validUntil < $asOf) {
                $excluded[$id] = 'validUntil_expired';
                continue;
            }

            if ($verification !== 'accepted') {
                $excluded[$id] = 'verification:' . $verification;
                continue;
            }

            if (!$this->isComparable($quote, $cycle)) {
                $excluded[$id] = 'incomparable_basis';
                continue;
            }

            $eligible[] = $quote;
        }

        $candidates = array_map(static fn ($q) => (string) $q->getId(), $eligible);

        if ($eligible === []) {
            return [
                'status' => self::STATUS_NEEDS_PRICE,
                'ruleVersion' => $policyVersion ?? self::RULE_VERSION,
                'asOf' => $asOf,
                'cycleId' => $cycleId,
                'intent' => (string) $cycle->get('intent'),
                'comparableKey' => $this->comparableKey($cycle),
                'candidates' => [],
                'excluded' => $excluded,
                'chosenQuoteId' => null,
                'chosenAmount' => null,
                'chosenCurrency' => null,
                'reason' => $quotes->count() === 0 ? 'no_quotes' : 'no_eligible_quote',
            ];
        }

        usort($eligible, function ($a, $b): int {
            // Amount comparison uses exact decimal strings via bccomp when
            // available, otherwise a normalized numeric compare that avoids
            // binary-float drift for same-scale decimals.
            $cmp = $this->compareAmount((string) $a->get('amount'), (string) $b->get('amount'));

            if ($cmp !== 0) {
                return $cmp;
            }

            $va = (string) ($a->get('verifiedAt') ?? '');
            $vb = (string) ($b->get('verifiedAt') ?? '');

            if ($va !== $vb) {
                return $vb <=> $va;
            }

            return strcmp((string) $a->getId(), (string) $b->getId());
        });

        $chosen = $eligible[0];

        return [
            'status' => self::STATUS_OK,
            'ruleVersion' => $policyVersion ?? self::RULE_VERSION,
            'asOf' => $asOf,
            'cycleId' => $cycleId,
            'intent' => (string) $cycle->get('intent'),
            'comparableKey' => $this->comparableKey($cycle),
            'candidates' => $candidates,
            'excluded' => $excluded,
            'chosenQuoteId' => (string) $chosen->getId(),
            'chosenAmount' => (string) $chosen->get('amount'),
            'chosenCurrency' => (string) $chosen->get('currency'),
            'reason' => 'lowest_eligible_comparable',
        ];
    }

    private function isComparable($quote, $cycle): bool
    {
        // Exact basis match with the owning cycle, never a loose mix.
        $key = $this->comparableKey($cycle);

        if ((string) $quote->get('scenario') !== $key['scenario']) {
            return false;
        }

        if ((string) $quote->get('unit') !== $key['unit']) {
            return false;
        }

        if ((string) $quote->get('currency') !== $key['currency']) {
            return false;
        }

        if (!in_array((string) $quote->get('scenario'), ['sale', 'month', 'year', 'payment_basis'], true)) {
            return false;
        }

        return true;
    }

    /** @return array<string, ?string> */
    private function comparableKey($cycle): array
    {
        return [
            'intent' => (string) $cycle->get('intent'),
            'currency' => 'VND',
            'unit' => 'total',
            'scenario' => (string) $cycle->get('intent') === 'RENT' ? 'month' : 'sale',
        ];
    }

    private function compareAmount(string $a, string $b): int
    {
        if (function_exists('bccomp')) {
            return bccomp($a, $b, 2);
        }

        // Exact fixed-scale fallback without binary floating point.
        $normalize = static function (string $value): string {
            $value = trim($value);
            $negative = str_starts_with($value, '-');
            $value = ltrim($value, '+-');
            [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
            $whole = ltrim($whole, '0') ?: '0';
            $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
            $digits = ltrim($whole . $fraction, '0') ?: '0';
            return ($negative && $digits !== '0' ? '-' : '') . str_pad($digits, 1, '0', STR_PAD_LEFT);
        };

        $na = $normalize($a);
        $nb = $normalize($b);
        $an = str_starts_with($na, '-');
        $bn = str_starts_with($nb, '-');
        if ($an !== $bn) {
            return $an ? -1 : 1;
        }
        $aa = ltrim($na, '-');
        $bb = ltrim($nb, '-');
        $cmp = strlen($aa) <=> strlen($bb);
        if ($cmp === 0) {
            $cmp = strcmp($aa, $bb);
        }
        return $an ? -$cmp : $cmp;
    }
}