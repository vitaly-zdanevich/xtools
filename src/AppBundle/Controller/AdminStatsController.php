<?php

/**
 * This file contains the code that powers the AdminStats page of XTools.
 *
 * @version 1.5.1
 */

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Xtools\ProjectRepository;

/**
 * Class AdminStatsController
 *
 * @category AdminStats
 * @package  AppBundle\Controller
 * @author   XTools Team <xtools@lists.wikimedia.org>
 * @license  GPL 3.0
 */
class AdminStatsController extends XtoolsController
{
    /**
     * Get the tool's shortname.
     * @return string
     * @codeCoverageIgnore
     */
    public function getToolShortname()
    {
        return 'adminstats';
    }

    /**
     * Method for rendering the AdminStats Main Form.
     * This method redirects if valid parameters are found, making it a
     * valid form endpoint as well.
     *
     * @param Request $request Generated by Symfony
     *
     * @Route("/adminstats",           name="adminstats")
     * @Route("/adminstats/",          name="AdminStatsSlash")
     * @Route("/adminstats/index.php", name="AdminStatsIndexPhp")
     *
     * @return Route|\Symfony\Component\HttpFoundation\Response
     */
    public function indexAction(Request $request)
    {
        $params = $this->parseQueryParams($request);

        // Redirect if we have a project. $results may also include start and/or end date.
        if (isset($params['project'])) {
            return $this->redirectToRoute('AdminStatsResult', $params);
        }

        // Otherwise render form.
        return $this->render('adminStats/index.html.twig', [
            'xtPage' => 'adminstats',
            'xtPageTitle' => 'tool-adminstats',
            'xtSubtitle' => 'tool-adminstats-desc',
        ]);
    }

    /**
     * Method for rendering the AdminStats Results
     *
     * @param Request $request The HTTP request.
     * @param string $project Project to run the results against
     * @param string $start   Date to start on.  Must parse by strtotime.
     * @param string $end     Date to end on.  Must parse by strtotime.
     *
     * @Route(
     *   "/adminstats/{project}/{start}/{end}", name="AdminStatsResult",
     *   requirements={"start" = "|\d{4}-\d{2}-\d{2}", "end" = "|\d{4}-\d{2}-\d{2}"}
     * )
     *
     * @return Route|\Symfony\Component\HttpFoundation\Response
     * @todo Move SQL to a model.
     * @codeCoverageIgnore
     */
    public function resultAction(Request $request, $project, $start = null, $end = null)
    {
        // Load the database information for the tool.
        // $projectData will be a redirect if the project is invalid.
        $projectData = $this->validateProject($project);
        if ($projectData instanceof RedirectResponse) {
            return $projectData;
        }

        list($start, $end) = $this->getUTCFromDateParams($start, $end);

        // Initialize variables - prevents variable undefined errors
        $adminIdArr = [];
        $adminsWithoutAction = 0;
        $adminsWithoutActionPct = 0;

        // Pull the API helper and database. Then check if we can use this tool
        $api = $this->get('app.api_helper');
        $conn = $this->get('doctrine')->getManager('replicas')->getConnection();

        // Generate a diff for the dates - this is the number of days we're spanning.
        $days = ($end - $start) / 60 / 60 / 24;

        // Get admin ID's, used to account for inactive admins
        $userGroupsTable = $projectData->getTableName('user_groups');
        $ufgTable = $projectData->getTableName('user_former_groups');
        $query = "
            SELECT ug_user AS user_id
            FROM $userGroupsTable
            WHERE ug_group = 'sysop'
            UNION
            SELECT ufg_user AS user_id
            FROM $ufgTable
            WHERE ufg_group = 'sysop'
            ";

        $res = $conn->prepare($query);
        $res->execute();

        // Iterate over query results, loading each user id into the array
        while ($row = $res->fetch()) {
            $adminIdArr[] = $row['user_id'] ;
        }

        // Set the query results to be useful in a sql statement.
        $adminIds = implode(',', $adminIdArr);

        // Load up the tables we need and run the mega query.
        // This query provides all of the statistics
        $userTable = $projectData->getTableName('user');
        $loggingTable = $projectData->getTableName('logging', 'userindex');

        $startDb = date('Ymd000000', $start);
        $endDb = date('Ymd235959', $end);

        // TODO: Fix this - inactive admins aren't getting shown
        $query = "
            SELECT user_name, user_id,
                SUM(IF( (log_type = 'delete'  AND log_action != 'restore'),1,0)) AS mdelete,
                SUM(IF( (log_type = 'delete'  AND log_action  = 'restore'),1,0)) AS mrestore,
                SUM(IF( (log_type = 'block'   AND log_action != 'unblock'),1,0)) AS mblock,
                SUM(IF( (log_type = 'block'   AND log_action  = 'unblock'),1,0)) AS munblock,
                SUM(IF( (log_type = 'protect' AND log_action != 'unprotect'),1,0)) AS mprotect,
                SUM(IF( (log_type = 'protect' AND log_action  = 'unprotect'),1,0)) AS munprotect,
                SUM(IF( log_type  = 'rights',1,0)) AS mrights,
                SUM(IF( log_type  = 'import',1,0)) AS mimport,
                SUM(IF(log_type  != '',1,0)) AS mtotal
            FROM $loggingTable
            JOIN $userTable ON user_id = log_user
            WHERE log_timestamp > '$startDb' AND log_timestamp <= '$endDb'
              AND log_type IS NOT NULL
              AND log_action IS NOT NULL
              AND log_type IN ('block', 'delete', 'protect', 'import', 'rights')
            GROUP BY user_name
            HAVING mdelete > 0 OR user_id IN ($adminIds)
            ORDER BY mtotal DESC";

        $res = $conn->prepare($query);
        $res->execute();

        // Fetch all the information out.  Because of pre-processing done
        // in the query, we can use this practically raw.
        $users = $res->fetchAll();

        // Pull the admins from the API, for merging.
        $admins = $api->getAdmins($project);

        // Get the total number of admins, the number of admins without
        // action, and then later we'll run percentage calculations
        $adminCount = count($admins);

        // Combine the two arrays.  We can't use array_merge here because
        // the arrays contain fundamentally different data.  Instead, it's
        // done by hand.  Only two values are needed, edit count and groups.
        foreach ($users as $key => $value) {
            $username = $value['user_name'];

            if (empty($admins[$username])) {
                $admins[$username] = [
                    'groups' => '',
                ];
            }
            $users[$key]['groups'] = $admins[$username]['groups'];

            if ($users[$key]['mtotal'] === 0) {
                $adminsWithoutAction++;
            }

            unset($admins[$username]);
        }

        // push any inactive admins back to $users with zero values
        if (count($admins)) {
            foreach ($admins as $username => $stats) {
                $users[] = [
                    'user_name' => $username,
                    'mdelete' => 0,
                    'mrestore' => 0,
                    'mblock' => 0,
                    'munblock' => 0,
                    'mprotect' => 0,
                    'munprotect' => 0,
                    'mrights' => 0,
                    'mimport' => 0,
                    'mtotal' => 0,
                    'groups' => $stats['groups'],
                ];
                $adminsWithoutAction++;
            }
        }

        if ($adminCount > 0) {
            $adminsWithoutActionPct = ($adminsWithoutAction / $adminCount) * 100;
        }

        // Render the result!
        return $this->render('adminStats/result.html.twig', [
            'xtPage' => 'adminstats',
            'xtTitle' => $project,

            'project' => $projectData,

            'start_date' => date('Y-m-d', $start),
            'end_date' => date('Y-m-d', $end),
            'days' => $days,

            'adminsWithoutAction' => $adminsWithoutAction,
            'admins_without_action_pct' => $adminsWithoutActionPct,
            'adminCount' => $adminCount,

            'users' => $users,
            'usersCount' => count($users),
        ]);
    }
}
