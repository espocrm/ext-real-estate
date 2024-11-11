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

namespace Espo\Modules\RealEstate\Tools\Matches;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;

use Espo\Core\Mail\EmailSender;
use Espo\Core\Mail\Exceptions\SendingError;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Record\ServiceContainer;
use Espo\Core\Utils\Config;

use Espo\Entities\Attachment;
use Espo\Entities\Email;

use Espo\Entities\User;
use Espo\Modules\Crm\Entities\Contact;
use Espo\Modules\RealEstate\Entities\RealEstateProperty;
use Espo\Modules\RealEstate\Entities\RealEstateRequest;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Service as EmailTemplateService;
use Exception;
use DateTime;

class Service
{
    public function __construct(
        private EmailSender $emailSender,
        private Config $config,
        private EntityManager $entityManager,
        private ServiceContainer $serviceContainer,
        private EmailTemplateService $emailTemplateService
    ) {}

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
            $item = $this->entityManager->getEntityById('RealEstateSendMatchesQueueItem', $item->getId());

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

    private function processCleanup(): void
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
                ->getRDBRepository('RealEstateSendMatchesQueueItem')
                ->deleteFromDb($item->getId());
        }
    }

    /**
     * @throws Error
     * @throws SendingError
     * @throws NotFound
     * @throws Forbidden
     */
    private function sendMatchesEmail($data): void
    {
        if (empty($data['requestId']) || empty($data['propertyId'])) {
            throw new NotFound();
        }

        /** @var ?RealEstateRequest $request */
        $request = $this->entityManager->getEntityById(RealEstateRequest::ENTITY_TYPE, $data['requestId']);

        /** @var ?RealEstateProperty $property */
        $property = $this->entityManager->getEntityById(RealEstateProperty::ENTITY_TYPE, $data['propertyId']);

        if (!$request || !$property) {
            throw new NotFound();
        }

        $templateId = $this->config->get('realEstatePropertyTemplateId');

        if (empty($templateId)) {
            throw new Error('RealEstate EmailSending[' . $request->getId() . ']: No Template in config');
        }

        $this->serviceContainer
            ->getByClass(RealEstateRequest::class)
            ->loadAdditionalFields($request);

        $this->serviceContainer
            ->getByClass(RealEstateProperty::class)
            ->loadAdditionalFields($request);

        $contactId = $request->get('contactId');

        if (!$contactId) {
            throw new Error('RealEstate EmailSending[' . $request->getId() . ']: No Contact in Request ');
        }

        $contact = $this->entityManager->getEntityById(Contact::ENTITY_TYPE, $contactId);

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
            $assignedUser = $this->entityManager->getEntityById(User::ENTITY_TYPE, $request->get('assignedUserId'));

            $entityHash['User'] = $assignedUser;

            if ($assignedUser->get('emailAddress')) {
                $ccEmailAddress = $assignedUser->get('emailAddress');
            }
        }

        $entityHash['Person'] = $contact;

        $data = Data::create()
            ->withEntityHash($entityHash)
            ->withEmailAddress($toEmailAddress);

        if ($request->hasAttribute('parentId') && $request->hasAttribute('parentType')) {
            $data = $data
                ->withParentType($request->get('parentType'))
                ->withParentId($request->get('parentId'));
        }

        $result = $this->emailTemplateService->process($templateId, $data);

        $emailBody = $result->getBody();

        $emailData = [
            'to' => $toEmailAddress,
            'subject' => $result->getSubject(),
            'body' => $emailBody,
            'isHtml' => $result->isHtml(),
            'parentId' => $request->getId(),
            'parentType' => $request->getEntityType(),
        ];

        if ($ccEmailAddress) {
            $emailData['cc'] = $ccEmailAddress;
        }

        $attachmentList = [];

        foreach ($result->getAttachmentIdList() as $attachmentId) {
            $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $attachmentId);

            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        foreach ($property->getLinkMultipleIdList('images') as $attachmentId) {
            $attachment = $this->entityManager->getEntityById('Attachment', $attachmentId);

            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        /** @var Email $email */
        $email = $this->entityManager->getNewEntity(Email::ENTITY_TYPE);

        $email->set($emailData);

        $emailSender = $this->emailSender->create();

        $emailSender
            ->withAttachments($attachmentList)
            ->send($email);
    }
}
