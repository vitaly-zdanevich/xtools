<?php

declare(strict_types = 1);

namespace AppBundle\Repository;

use AppBundle\Model\Page;
use AppBundle\Model\Project;

/**
 * A TransclusionCounterRepository is responsible for retrieving information from the databases
 * for the Transclusion Counter tool. It does not do any post-processing of that data.
 * @codeCoverageIgnore
 */
class TransclusionCounterRepository extends Repository
{
    /**
     * @param Page $page
     * @param string|int $namespace
     * @return int|null
     */
    public function getTransclusionCounts(Page $page, $namespace = 'all'): ?int
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'page_transclusioncounter');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $project = $page->getProject();
        $pageTitle = str_replace(' ', '_', $page->getTitleWithoutNamespace());
        $pageTable = $project->getTableName('page');
        $templatelinksTable = $page->getProject()->getTableName('templatelinks');
        $sql = "SELECT COUNT(*)
                FROM $pageTable
                JOIN $templatelinksTable ON page_title = tl_title
                    AND page_namespace = tl_namespace
                WHERE page_title = :pageTitle
                    AND page_namespace = :pageNs";

        if ('all' !== $namespace) {
            $sql .= "\nAND tl_from_namespace = :fromNs";
        }

        $result = $this->executeProjectsQuery($sql, [
            'pageTitle' => $pageTitle,
            'pageNs' => $page->getNamespace(),
            'fromNs' => $namespace,
        ])->fetchColumn();

        return $this->setCache($cacheKey, false === $result ? null : (int)$result);
    }

    /**
     * Get the localized protection types for the given Page.
     * @param Page $page
     * @param string $lang
     * @return string[]
     */
    public function getProtectionTypes(Page $page, string $lang): array
    {
        $cacheKey = $this->getCacheKey(func_get_args(), 'project_restrictiontypes');
        if ($this->cache->hasItem($cacheKey)) {
            return $this->cache->getItem($cacheKey)->get();
        }

        $messageKeys = array_map(function ($key) {
            return "restriction-$key";
        }, array_unique(array_column($page->getProtections(), 'type')));
        $params = [
            'action' => 'query',
            'meta' => 'allmessages',
            'ammessages' => implode('|', $messageKeys),
            'amlang' => $lang,
            'amenableparser' => 1,
            'formatversion' => 2,
        ];

        return $this->setCache($cacheKey, array_column(
            $this->executeApiRequest($page->getProject(), $params)['query']['allmessages'],
            'content'
        ));
    }
}
