<?php

/**
 * Pimcore
 *
 * This source file is available under two different licenses:
 * - GNU General Public License version 3 (GPLv3)
 * - Pimcore Commercial License (PCL)
 * Full copyright and license information is available in
 * LICENSE.md which is distributed with this source code.
 *
 *  @copyright  Copyright (c) Pimcore GmbH (http://www.pimcore.org)
 *  @license    http://www.pimcore.org/license     GPLv3 and PCL
 */

namespace Pimcore\Bundle\CoreBundle\EventListener\Frontend;

use Pimcore\Bundle\CoreBundle\EventListener\Traits\PimcoreContextAwareTrait;
use Pimcore\Bundle\CoreBundle\EventListener\Traits\StaticPageContextAwareTrait;
use Pimcore\Document\StaticPageGenerator;
use Pimcore\Event\DocumentEvents;
use Pimcore\Event\Model\DocumentEvent;
use Pimcore\Http\Request\Resolver\DocumentResolver;
use Pimcore\Http\Request\Resolver\PimcoreContextResolver;
use Pimcore\Http\RequestHelper;
use Pimcore\Logger;
use Pimcore\Model\Document\Page;
use Pimcore\Model\Document\PageSnippet;
use Pimcore\Bundle\CoreBundle\EventListener\Traits\StaticPageResolverTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
class StaticPageGeneratorListener implements EventSubscriberInterface
{
    use PimcoreContextAwareTrait;
    use StaticPageContextAwareTrait;

    /**
     * @var StaticPageGenerator
     */
    protected $staticPageGenerator;

    /**
     * @var DocumentResolver
     */
    protected $documentResolver;


    /**
     * @var RequestHelper
     */
    protected $requestHelper;

    public function __construct(StaticPageGenerator $staticPageGenerator, DocumentResolver $documentResolver, RequestHelper $requestHelper)
    {
        $this->staticPageGenerator = $staticPageGenerator;
        $this->documentResolver = $documentResolver;
        $this->requestHelper = $requestHelper;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents()
    {
        return [
            DocumentEvents::POST_ADD => 'onPostAddUpdateDeleteDocument',
            DocumentEvents::POST_DELETE => 'onPostAddUpdateDeleteDocument',
            DocumentEvents::POST_UPDATE => 'onPostAddUpdateDeleteDocument',
            KernelEvents::REQUEST => ['onKernelRequest' , 10], //this must run before targeting listener
            KernelEvents::RESPONSE => ['onKernelResponse', -120], //this must run after code injection listeners
        ];
    }

    /**
     * @param RequestEvent $event
     */
    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        if (\Pimcore\Tool::isFrontendRequestByAdmin($request)) {
            return;
        }

        $document = $this->documentResolver->getDocument();

        if ($document instanceof Page && $document->getStaticGeneratorEnabled()) {
            $this->staticPageResolver->setStaticPageContext($request);
        }
    }

    /**
     * @param ResponseEvent $event
     */
    public function onKernelResponse(ResponseEvent $event)
    {
        $request = $event->getRequest();

        if (\Pimcore\Tool::isFrontendRequestByAdmin($request)) {
            return;
        }

        //return if request is from StaticPageGenerator
        if ($request->attributes->has('static_page_generator')) {
            return;
        }

        // only inject analytics code on non-admin requests
        if (!$this->matchesPimcoreContext($request, PimcoreContextResolver::CONTEXT_DEFAULT)
            && !$this->matchesStaticPageContext($request)) {
            return;
        }

        //return if request is from StaticPageGenerator
        if ($request->attributes->has('static_page_generator')) {
            return;
        }

        $document = $this->documentResolver->getDocument();

        if ($document instanceof Page && $document->getStaticGeneratorEnabled()) {
            $response = $event->getResponse()->getContent();
            $this->staticPageGenerator->generate($document, ['response' => $response]);
        }
    }

    /**
     * @param DocumentEvent $e
     */
    public function onPostAddUpdateDeleteDocument(DocumentEvent $e)
    {
        $document = $e->getDocument();

        if($e->hasArgument('saveVersionOnly') || $e->hasArgument('autoSave')) {
            return;
        }

        if ($document instanceof PageSnippet) {
            try {
                if($document->getStaticGeneratorEnabled()
                    || $this->staticPageGenerator->pageExists($document)) {
                    $this->staticPageGenerator->remove($document);
                }
            } Catch(\Exception $e) {
                Logger::error($e);

                return;
            }
        }
    }
}
