<?php
/************************************************************************
 * This file is part of Real Estate extension for EspoCRM.
 *
 * Demo Data extension for EspoCRM.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * Demo Data extension is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Demo Data extension is distributed in the hope that it will be useful,
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
 ************************************************************************/

namespace Espo\Modules\RealEstate\Services;

use \Espo\Core\Exceptions\Forbidden;
use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\NotFound;
use \Espo\Core\Exceptions\Error;

class RealEstateSendMatches extends \Espo\Core\Templates\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependencyList([
            'serviceFactory',
            'container',
            'preferences',
            'crypt'
        ]);
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getPreferences()
    {
        return $this->getInjection('preferences');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getMailSender()
    {
        return $this->getInjection('container')->get('mailSender');
    }

    protected function getSmptParams()
    {
        return $this->getPreferences()->getSmtpParams();
    }

    public function processRequestJob($data)
    {
        if (empty($data->targetId)) throw new Error();
        $entity = $this->getEntityManager()->getEntity('RealEstateRequest', $data->targetId);
        if (!$entity) throw new NotFound();

        $service = $this->getServiceFactory()->create($entity->getEntityType());
        $service->loadAdditionalFields($entity);

        $selectParams = $service->getMatchingPropertiesSelectParams($entity, []);

        $selectParams['offset'] = 0;
        $selectParams['limit'] = $this->getConfig()->get('realEstateEmailSendingLimit', 20);
        $selectParams['orderBy'] = [
            ['propertiesMiddle.interest_degree'],
            ['LIST:status:New,Assigned,In Process'],
            ['createdAt', 'DESC']
        ];

        $propertyList = $this->getEntityManager()->getRepository('RealEstateProperty')->find($selectParams);

        foreach ($propertyList as $property) {
            if (
                $this->getEntityManager()->getRepository('RealEstateSendMatchesQueueItem')->where([
                    'requestId' => $entity->id,
                    'propertyId' => $property->id
                ])->findOne()
            ) continue;

            $queueItem = $this->getEntityManager()->getEntity('RealEstateSendMatchesQueueItem');
            $queueItem->set([
                'requestId' => $entity->id,
                'propertyId' => $property->id
            ]);
            $this->getEntityManager()->saveEntity($queueItem);
        }

        return true;
    }

    public function processPropertyJob($data)
    {
        if (empty($data->targetId)) throw new Error();
        $entity = $this->getEntityManager()->getEntity('RealEstateProperty', $data->targetId);
        if (!$entity) throw new NotFound();

        $service = $this->getServiceFactory()->create($entity->getEntityType());
        $service->loadAdditionalFields($entity);

        $selectParams = $service->getMatchingRequestsSelectParams($entity, []);

        $selectParams['offset'] = 0;
        $selectParams['limit'] = $this->getConfig()->get('realEstateEmailSendingLimit', 20);
        $selectParams['orderBy'] = [
            ['requestsMiddle.interest_degree'],
            ['LIST:status:New,Assigned,In Process'],
            ['createdAt', 'DESC']
        ];

        $requestList = $this->getEntityManager()->getRepository('RealEstateRequest')->find($selectParams);

        foreach ($requestList as $request) {
            if (!$request->get('contactId')) continue;
            if (
                $this->getEntityManager()->getRepository('RealEstateSendMatchesQueueItem')->where([
                    'propertyId' => $entity->id,
                    'requestId' => $request->id
                ])->findOne()
            ) continue;

            $queueItem = $this->getEntityManager()->getEntity('RealEstateSendMatchesQueueItem');
            $queueItem->set([
                'propertyId' => $entity->id,
                'requestId' => $request->id
            ]);
            $this->getEntityManager()->saveEntity($queueItem);
        }

        return true;
    }

    public function processSendingQueue()
    {
        $limit = $this->getConfig()->get('realEstateEmailSendingPortionSize', 30);
        $itemList = $this->getEntityManager()->getRepository('RealEstateSendMatchesQueueItem')->order('createdAt')->where([
            'isProcessed' => false
        ])->limit(0, $limit)->find();

        foreach ($itemList as $item) {
            $item = $this->getEntityManager()->getEntity('RealEstateSendMatchesQueueItem', $item->id);
            if ($item->get('isProcessed')) continue;
            $item->set('isProcessed', true);
            $this->getEntityManager()->saveEntity($item);
            try {
                $this->sendMatchesEmail([
                    'requestId' => $item->get('requestId'),
                    'propertyId' => $item->get('propertyId')
                ]);
            } catch (\Exception $e) {}
        }

        $this->processCleanup();
    }

    public function processCleanup()
    {
        $period = '-' . $this->getConfig()->get('realEstateEmailSendingCleanupPeriod', '3 months');
        $datetime = new \DateTime();
        $datetime->modify($period);

        $itemList = $this->getEntityManager()->getRepository('RealEstateSendMatchesQueueItem')->where([
            'isProcessed' => true,
            'createdAt<' => $datetime->format('Y-m-d H:i:s')
        ])->find();
        foreach ($itemList as $item) {
            $this->getEntityManager()->getRepository('RealEstateSendMatchesQueueItem')->deleteFromDb($item->id);
        }
    }

    public function sendMatchesEmail($data)
    {
        if (empty($data['requestId']) || empty($data['propertyId'])) {
            throw new NotFound();
        }
        $request = $this->getEntityManager()->getEntity('RealEstateRequest', $data['requestId']);
        $property = $this->getEntityManager()->getEntity('RealEstateProperty', $data['propertyId']);

        if (!$request || !$property) {
            throw new NotFound();
        }

        $templateId = $this->getConfig()->get('realEstatePropertyTemplateId');
        if (empty($templateId)) {
            throw new Error('RealEstate EmailSending[' . $request->id . ']: No Template in config');
        }

        $emailBody = '';

        $requestService = $this->getServiceFactory()->create($request->getEntityType());
        $requestService->loadAdditionalFields($request);

        $propertyService = $this->getServiceFactory()->create($property->getEntityType());
        $propertyService->loadAdditionalFields($property);

        $contactId = $request->get('contactId');
        if (!$contactId) {
            throw new Error('RealEstate EmailSending[' . $request->id . ']: No Contact in Request ');
        }
        $contact = $this->getEntityManager()->getEntity('Contact', $contactId);
        if (!$contact) {
            throw new Error('RealEstate EmailSending[' . $request->id . ']: Contact not found');
        }
        if (!$contact->get('emailAddress')) {
            throw new Error('RealEstate EmailSending[' . $request->id . ']: Contact has no email address');
        }
        $toEmailAddress = $contact->get('emailAddress');
        if (!$toEmailAddress) return;
        $ccEmailAddress = false;

        $entityHash = [];
        $entityHash[$request->getEntityType()] = $request;
        $entityHash[$property->getEntityType()] = $property;

        if ($this->getConfig()->get('realEstateEmailSendingAssignedUserCc') && $request->get('assignedUserId')) {
            $assignedUser = $this->getEntityManager()->getEntity('User', $request->get('assignedUserId'));
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
        if ($request->hasField('parentId') && $request->hasField('parentType')) {
            $emailTemplateParams['parentId'] = $request->get('parentId');
            $emailTemplateParams['parentType'] = $request->get('parentType');
        }

        $emailTemplateService = $this->getServiceFactory()->create('EmailTemplate');
        $emailTemplate = $emailTemplateService->parse($templateId, $emailTemplateParams, true);
        $emailBody = $emailTemplate['body'];

        $emailData = array(
            'to' => $toEmailAddress,
            'subject' => $emailTemplate['subject'],
            'body' => $emailBody,
            'isHtml' => $emailTemplate['isHtml'],
            'parentId' => $request->id,
            'parentType' => $request->getEntityType()
        );
        if ($ccEmailAddress) {
            $emailData['cc'] = $ccEmailAddress;
        }

        $attachmentList = [];

        foreach ($emailTemplate['attachmentsIds'] as $attachmentId) {
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        foreach ($property->getLinkMultipleIdList('images') as $attachmentId) {
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if ($attachment) {
                $attachmentList[] = $attachment;
            }
        }

        $email = $this->getEntityManager()->getEntity('Email');
        $email->set($emailData);

        $emailSender = $this->getMailSender();
        if ($this->getSmptParams()) {
            $emailSender->useSmtp($this->getSmptParams());
        }

        $message = new \Zend\Mail\Message();
        $emailSender->send($email, array(), $message, $attachmentList);
    }
}
