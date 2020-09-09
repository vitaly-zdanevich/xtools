<?php

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\TransclusionCounter;
use AppBundle\Repository\TransclusionCounterRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TransclusionCounterController extends XtoolsController
{
    /**
     * Get the name of the tool's index route. This is also the name of the associated model.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'TransclusionCounter';
    }

    /**
     * Form for the Transclusion Counter tool.
     * @Route("/tc", name="TransclusionCounter")
     * @Route("/tc/{project}", name="TransclusionCounterProject")
     * @return Response
     */
    public function indexAction(): Response
    {
        if (isset($this->params['project']) && isset($this->params['page'])) {
            return $this->redirectToRoute('TransclusionCounterResult', $this->params);
        }

        return $this->render('transclusionCounter/index.html.twig', array_merge([
            'xtPage' => 'TransclusionCounter',
            'xtPageTitle' => 'tool-transclusioncounter',
            'xtSubtitle' => 'tool-transclusioncounter-desc',
            'project' => $this->project,
            'page' => '',
            'trans_namespace' => 'all',
        ], $this->params, ['project' => $this->project]));
    }

    /**
     * Setup the TransclusionCounter object.
     * @param string|int $transNamespace
     * @return TransclusionCounter
     */
    private function setupTransclusionCounter($transNamespace): TransclusionCounter
    {
        $tcRepo = new TransclusionCounterRepository();
        $tcRepo->setContainer($this->container);
        $tc = new TransclusionCounter($this->page, $transNamespace, $this->i18n->getLang());
        $tc->setRepository($tcRepo);
        return $tc;
    }

    /**
     * Display the results.
     * @Route(
     *     "/tc/{project}/{page}/{trans_namespace}",
     *     name="TransclusionCounterResult",
     *     requirements={
     *         "page"="(.+?)",
     *         "namespace" = "|all|\d+"
     *     },
     *     defaults={"trans_namespace"="all"}
     * )
     * @return Response
     * @codeCoverageIgnore
     */
    public function resultAction(): Response
    {
        return $this->getFormattedResponse('transclusionCounter/result', [
            'xtPage' => 'TransclusionCounter',
            'xtTitle' => $this->page->getTitle(),
            'tc' => $this->setupTransclusionCounter($this->params['trans_namespace']),
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * API endpoint.
     * @Route(
     *     "/api/page/transclusion_count/{project}/{page}/{trans_namespace}",
     *     name="PageApiTransclusionCounter",
     *     requirements={
     *         "page"="(.+?)",
     *         "trans_namespace" = "|all|\d+"
     *     },
     *     defaults={"trans_namespace"="all"}
     * )
     * @return JsonResponse
     */
    public function transclusionEditCounterApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'count' => $this->setupTransclusionCounter($this->i18n->getLang())->getCount(),
        ]);
    }
}
