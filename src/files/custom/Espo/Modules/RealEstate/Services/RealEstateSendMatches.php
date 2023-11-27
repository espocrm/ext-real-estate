<?php
/************************************************************************
 * This file is part of Real Estate extension for EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * Real Estate extension is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Real Estate extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\RealEstate\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;

use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ServiceFactory;
use Espo\Core\Utils\Config;

use Espo\Core\Select\SearchParams;
use Espo\Entities\Email;
use Espo\ORM\Query\Part\Order;
use Espo\ORM\Query\Part\Expression as Expr;

use Exception;
use DateTime;
use stdClass;

class RealEstateSendMatches
{
    private ServiceFactory $serviceFactory;
    private EmailSender $emailSender;
    private Config $config;
    private EntityManager $entityManager;

    public function __construct(
        ServiceFactory $serviceFactory,
        EmailSender $emailSender,
        Config $config,
        EntityManager $entityManager
    ) {
        $this->serviceFactory = $serviceFactory;
        $this->emailSender = $emailSender;
        $this->config = $config;
        $this->entityManager = $entityManager;
    }

    /**
     * @throws NotFound
     * @throws Error
     */
    public function processRequestJob(stdClass $data): void
    {
        if (empty($data->targetId)) {
            throw new Error();
        }

        $entity = $this->entityManager->getEntity('RealEstateRequest', $data->targetId);

        if (!$entity) {
            throw new NotFound();
        }

        $service = $this->serviceFactory->create($entity->getEntityType());

        $service->loadAdditionalFields($entity);

        $query = $service->getMatchingPropertiesQuery($entity, SearchParams::create());

        $limit = $this->config->get('realEstateEmailSendingLimit', 20);

        $propertyList = $this->entityManager
            ->getRDBRepository('RealEstateProperty')
            ->clone($query)
            ->limit(0, $limit)
            ->order([
                Order::fromString('propertiesMiddle.interestDegree'),
                Order::createByPositionInList(Expr::create('status'), ['New', 'Assigned', 'In Process']),
                Order::fromString('createdAt')->withDesc(),
            ])
            ->find();

        foreach ($propertyList as $property) {
            if (
                $this->entityManager
                    ->getRDBRepository('RealEstateSendMatchesQueueItem')
                    ->where([
                        'requestId' => $entity->getId(),
                        'propertyId' => $property->getId()
                    ])
                    ->findOne()
            ) {
                continue;
            }

            $queueItem = $this->entityManager->getEntity('RealEstateSendMatchesQueueItem');

            $queueItem->set([
                'requestId' => $entity->getId(),
                'propertyId' => $property->getId()
            ]);

            $this->entityManager->saveEntity($queueItem);
        }
    }

    /**
     * @throws NotFound
     * @throws Error
     */
    public function processPropertyJob(stdClass $data): void
    {
        if (empty($data->targetId)) {
            throw new Error();
        }

        $entity = $this->entityManager->getEntity('RealEstateProperty', $data->targetId);

        if (!$entity) {
            throw new NotFound();
        }

        $service = $this->serviceFactory->create($entity->getEntityType());

        $service->loadAdditionalFields($entity);

        $query = $service->getMatchingRequestsQuery($entity, SearchParams::create());

        $limit = $this->config->get('realEstateEmailSendingLimit', 20);

        $requestList = $this->entityManager
            ->getRDBRepository('RealEstateRequest')
            ->clone($query)
            ->limit(0, $limit)
            ->order([
                Order::fromString('requestsMiddle.interestDegree'),
                Order::createByPositionInList(Expr::create('status'), ['New', 'Assigned', 'In Process']),
                Order::fromString('createdAt')->withDesc(),
            ])
            ->find();

        foreach ($requestList as $request) {
            if (!$request->get('contactId')) {
                continue;
            }

            if (
                $this->entityManager
                    ->getRDBRepository('RealEstateSendMatchesQueueItem')
                    ->where([
                        'propertyId' => $entity->getId(),
                        'requestId' => $request->getId(),
                    ])
                    ->findOne()
            ) {
                continue;
            }

            $queueItem = $this->entityManager->getEntity('RealEstateSendMatchesQueueItem');

            $queueItem->set([
                'propertyId' => $entity->getId(),
                'requestId' => $request->getId()
            ]);

            $this->entityManager->saveEntity($queueItem);
        }
    }

    public function processSendingQueue(): void
    {
        $limit = $this->config->get('realEstateEmailSendingPortionSize', 30);

        $itemList = $this->entityManager
            ->getRDBRepository('RealEstateSendMatchesQueueItem')
            ->order('createdAt')
            ->where([
                'isProcessed' => false
            ])
            ->limit(0, $limit)
            ->find();

        foreach ($itemList as $item) {
            $item = $this->entityManager->getEntity('RealEstateSendMatchesQueueItem', $item->getId());

            if ($item->get('isProcessed')) {
                continue;
            }

            $item->set('isProcessed', true);

            $this->entityManager->saveEntity($item);

            try {
                $this->sendMatchesEmail([
                    'requestId' => $item->get('requestId'),
                    'propertyId' => $item->get('propertyId'),
                ]);
            }
            catch (Exception) {}
        }

        $this->processCleanup();
    }

    public function processCleanup(): void
    {
        $period = '-' . $this->config->get('realEstateEmailSendingCleanupPeriod', '3 months');

        $datetime = new DateTime();
        $datetime->modify($period);

        $itemList = $this->entityManager
            ->getRDBRepository('RealEstateSendMatchesQueueItem')
            ->where([
                'isProcessed' => true,
                'createdAt<' => $datetime->format('Y-m-d H:i:s')
            ])
            ->find();

        foreach ($itemList as $item) {
            $this->entityManager
                ->getRepository('RealEstateSendMatchesQueueItem')
                ->deleteFromDb($item->getId());
        }
    }

    /**
     * @throws Error
     * @throws SendingError
     * @throws NotFound
     */
    public function sendMatchesEmail($data): void
    {
        if (empty($data['requestId']) || empty($data['propertyId'])) {
            throw new NotFound();
        }

        $request = $this->entityManager->getEntity('RealEstateRequest', $data['requestId']);
        $property = $this->entityManager->getEntity('RealEstateProperty', $data['propertyId']);

        if (!$request || !$property) {
            throw new NotFound();
        }

        $templateId = $this->config->get('realEstatePropertyTemplateId');

        if (empty($templateId)) {
            throw new Error('RealEstate EmailSending[' . $request->getId() . ']: No Template in config');
        }

        $requestService = $this->serviceFactory->create($request->getEntityType());
        $requestService->loadAdditionalFields($request);

        $propertyService = $this->serviceFactory->create($property->getEntityType());
        $propertyService->loadAdditionalFields($property);

        $contactId = $request->get('contactId');

        if (!$contactId) {
            throw new Error('RealEstate EmailSending[' . $request->getId() . ']: No Contact in Request ');
        }

        $contact = $this->entityManager->getEntity('Contact', $contactId);

        if (!$contact) {
            throw new Error('RealEstate EmailSending[' . $request->getId() . ']: Contact not found');
        }

        if (!$contact->get('emailAddress')) {
            throw new Error('RealEstate EmailSending[' . $request->getId() . ']: Contact has no email address');
        }

        $toEmailAddress = $contact->get('emailAddress');

        if (!$toEmailAddress) {
            return;
        }

        $ccEmailAddress = false;

        $entityHash = [];
        $entityHash[$request->getEntityType()] = $request;
        $entityHash[$property->getEntityType()] = $property;

        if ($this->config->get('realEstateEmailSendingAssignedUserCc') && $request->get('assignedUserId')) {
            $assignedUser = $this->entityManager->getEntity('User', $request->get('assignedUserId'));

            $entityHash['User'] = $assignedUser;

            if ($assignedUser->get('emailAddress')) {
                $ccEmailAddress = $assignedUser->get('emailAddress');
            }
        }

        $entityHash['Person'] = $contact;

        $emailTemplateParams = [
            'entityHash' => $entityHash,
            'emailAddress' => $toEmailAddress
        ];
        if ($request->hasAttribute('parentId') && $request->hasAttribute('parentType')) {
            $emailTemplateParams['parentId'] = $request->get('parentId');
            $emailTemplateParams['parentType'] = $request->get('parentType');
        }

        $emailTemplateService = $this->serviceFactory->create('EmailTemplate');
        $emailTemplate = $emailTemplateService->parse($templateId, $emailTemplateParams, true);
        $emailBody = $emailTemplate['body'];

        $emailData = [
            'to' => $toEmailAddress,
            'subject' => $emailTemplate['subject'],
            'body' => $emailBody,
            'isHtml' => $emailTemplate['isHtml'],
            'parentId' => $request->getId(),
            'parentType' => $request->getEntityType(),
        ];

        if ($ccEmailAddress) {
            $emailData['cc'] = $ccEmailAddress;
        }

        $attachmentList = [];

        foreach ($emailTemplate['attachmentsIds'] as $attachmentId) {
            $attachment = $this->entityManager->getEntity('Attachment', $attachmentId);

            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        foreach ($property->getLinkMultipleIdList('images') as $attachmentId) {
            $attachment = $this->entityManager->getEntity('Attachment', $attachmentId);

            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        /** @var Email $email */
        $email = $this->entityManager->getEntity('Email');

        $email->set($emailData);

        $emailSender = $this->emailSender->create();

        $emailSender
            ->withAttachments($attachmentList)
            ->send($email);
    }
}
