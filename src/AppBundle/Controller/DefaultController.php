<?php
/**
 * This file contains only the DefaultController class.
 */

declare(strict_types=1);

namespace AppBundle\Controller;

use AppBundle\Model\Edit;
use AppBundle\Repository\ProjectRepository;
use MediaWiki\OAuthClient\Client;
use MediaWiki\OAuthClient\ClientConfig;
use MediaWiki\OAuthClient\Consumer;
use MediaWiki\OAuthClient\Exception;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * The DefaultController handles the homepage, about pages, and user authentication.
 */
class DefaultController extends XtoolsController
{
    /** @var Client The Oauth HTTP client. */
    protected $oauthClient;

    /**
     * Required to be defined by XtoolsController, though here it is unused.
     * @return string
     * @codeCoverageIgnore
     */
    public function getIndexRoute(): string
    {
        return 'homepage';
    }

    /**
     * Display the homepage.
     * @Route("/", name="homepage")
     * @Route("/index.php", name="homepageIndexPhp")
     * @return Response
     */
    public function indexAction(): Response
    {
        return $this->render('default/index.html.twig', [
            'xtPage' => 'home',
        ]);
    }

    /**
     * Diplay XTools' about page.
     * @Route("/about", name="aboutPage")
     * @Route("/info.php", name="info")
     * @return Response
     */
    public function aboutAction(): Response
    {
        return $this->render('default/about.html.twig', [
            'xtPage' => 'about',
        ]);
    }

    /**
     * Display some configuration details, when in development mode.
     * @Route("/config", name="configPage")
     * @return Response
     * @codeCoverageIgnore
     */
    public function configAction(): Response
    {

        if ('dev' !== $this->container->getParameter('kernel.environment')) {
            throw new NotFoundHttpException();
        }

        $params = $this->container->getParameterBag()->all();

        foreach (array_keys($params) as $key) {
            if (false !== strpos($key, 'password')) {
                $params[$key] = '<REDACTED>';
            }
        }

        // replace this example code with whatever you need
        return $this->render('default/config.html.twig', [
            'xtTitle' => 'Config',
            'xtPageTitle' => 'Config',
            'xtPage' => 'index',
            'dump' => print_r($params, true),
        ]);
    }

    /************************ API endpoints ************************/

    /**
     * Get domain name, URL, and API URL of the given project.
     * @Route("/api/project/normalize/{project}", name="ProjectApiNormalize")
     * @return JsonResponse
     */
    public function normalizeProjectApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'domain' => $this->project->getDomain(),
            'url' => $this->project->getUrl(),
            'api' => $this->project->getApiUrl(),
            'database' => $this->project->getDatabaseName(),
        ]);
    }

    /**
     * Get all namespaces of the given project. This endpoint also does the same thing
     * as the /project/normalize endpoint, returning other basic info about the project.
     * @Route("/api/project/namespaces/{project}", name="ProjectApiNamespaces")
     * @return JsonResponse
     */
    public function namespacesApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'domain' => $this->project->getDomain(),
            'url' => $this->project->getUrl(),
            'api' => $this->project->getApiUrl(),
            'database' => $this->project->getDatabaseName(),
            'namespaces' => $this->project->getNamespaces(),
        ]);
    }

    /**
     * Get assessment data for a given project.
     * @Route("/api/project/assessments/{project}", name="ProjectApiAssessments")
     * @return JsonResponse
     */
    public function projectAssessmentsApiAction(): JsonResponse
    {
        return $this->getFormattedApiResponse([
            'project' => $this->project->getDomain(),
            'assessments' => $this->project->getPageAssessments()->getConfig(),
        ]);
    }

    /**
     * Get assessment data for all projects.
     * @Route("/api/project/assessments", name="ApiAssessmentsConfig")
     * @return JsonResponse
     */
    public function assessmentsConfigApiAction(): JsonResponse
    {
        // Here there is no Project, so we don't use XtoolsController::getFormattedApiResponse().
        $response = new JsonResponse();
        $response->setEncodingOptions(JSON_NUMERIC_CHECK);
        $response->setStatusCode(Response::HTTP_OK);
        $response->setData([
            'projects' => array_keys($this->container->getParameter('assessments')),
            'config' => $this->container->getParameter('assessments'),
        ]);

        return $response;
    }

    /**
     * Transform given wikitext to HTML using the XTools parser. Wikitext must be passed in as the query 'wikitext'.
     * @Route("/api/project/parser/{project}")
     * @return JsonResponse Safe HTML.
     */
    public function wikifyApiAction(): JsonResponse
    {
        return new JsonResponse(
            Edit::wikifyString($this->request->query->get('wikitext', ''), $this->project)
        );
    }
}
