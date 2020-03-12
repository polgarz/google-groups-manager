<?php
namespace polgarz\googlegroups\commands;

use Yii;
use yii\console\Controller;
use yii\helpers\ArrayHelper;
use yii\helpers\Console;
use yii\console\widgets\Table;

/**
 * Google Group members management controller
 */
class MembersController extends Controller
{
    /**
     * Syncronize members by the module 'group' attribute
     *
     * @return void
     */
    public function actionSyncronize()
    {
        $module = $this->module;
        $client = $module->client;

        $service = new \Google_Service_Directory($client);

        foreach ($module->groups as $group) {
            $model = Yii::createObject($group['model']);
            $query = $model::find();
            $list = $service->members->listMembers($group['groupKey']);
            $members = $list->getMembers();

            if (isset($group['scope']) && is_callable($group['scope'])) {
                call_user_func($group['scope'], $query);
            }

            $googleGroupEmails = ArrayHelper::getColumn($members, 'email');
            $ownEmails = $query->column();

            $delete = array_diff($googleGroupEmails, $ownEmails);
            $insert = array_diff($ownEmails, $googleGroupEmails);

            if ($delete) {
                foreach ($delete as $email) {
                    $this->stdout('Delete email address from Google Group (' . $group['groupKey'] . '): ' . $email . PHP_EOL, Console::FG_RED);

                    try {
                        $service->members->delete($group['groupKey'], $email);
                    } catch (\Google_Service_Exception $e) {
                        $this->stdout('Something went wrong when tried to delete a member: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
                    }
                }
            }

            if ($insert) {
                foreach ($insert as $email) {
                    $this->stdout('Insert email address into Google Group (' . $group['groupKey'] . '): ' . $email . PHP_EOL, Console::FG_GREEN);
                    $member = new \Google_Service_Directory_Member();
                    $member->email = $email;
                    $member->deliverySettings = 'ALL_MAIL';
                    $member->role = 'MEMBER';
                    $member->status = 'ACTIVE';

                    try {
                        $service->members->insert($group['groupKey'], $member);
                    } catch (\Google_Service_Exception $e) {
                        $this->stdout('Something went wrong when tried to insert a new member: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
                    }
                }
            }
        }
    }

    /**
     * List users in a group
     *
     * @param $groupKey The key of the group (eg. group email)
     * @param $printAsTable Print as table, or csv
     *
     * @return void
     */
    public function actionList($groupKey, $printAsTable = true)
    {
        $module = $this->module;
        $client = $module->client;
        $service = new \Google_Service_Directory($client);

        $list = $service->members->listMembers($groupKey);
        $members = $list->getMembers();
        $table = new Table();

        $keys = ['deliverySettings', 'email', 'etag', 'id', 'kind', 'role', 'status', 'type'];

        if ($members) {
            $rows = [];
            foreach ($members as $i => $member) {
                $row = [];
                foreach ($keys as $key) {
                    $row[$key] = $member->$key;
                }
                $rows[] = $row;
            }

            if ($printAsTable) {
                $this->stdout($table
                    ->setHeaders($keys)
                    ->setRows($rows)
                    ->run());
            } else {
                $this->stdout(implode(',', $keys) . PHP_EOL);
                foreach ($rows as $row) {
                    $this->stdout(implode(',', $row) . PHP_EOL);
                }
            }
        } else {
            $this->stdout('There are no members in this group' . PHP_EOL);
        }
    }

    /**
     * Adds a member to a group
     *
     * @param $groupKey The key of the group (eg. group email)
     * @param $memberId Member ID (eg. member email)
     * @param $deliverySettings
     * @param $role
     * @param $status
     *
     * @return void
     */
    public function actionAdd($groupKey, $memberId, $deliverySettings = 'ALL_MAIL', $role = 'MEMBER', $status = 'ACTIVE')
    {
        $module = $this->module;
        $client = $module->client;
        $service = new \Google_Service_Directory($client);

        $member = new \Google_Service_Directory_Member();
        $member->email = $memberId;
        $member->deliverySettings = $deliverySettings;
        $member->role = $role;
        $member->status = $status;

        try {
            $service->members->insert($groupKey, $member);
            $this->stdout('Successfully inserted a member' . PHP_EOL, Console::FG_GREEN);
        } catch (\Google_Service_Exception $e) {
            $this->stdout('Something went wrong when tried to insert a new member: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
        }
    }

    /**
     * Deletes a group member
     *
     * @param $groupKey The key of the group (eg. group email)
     * @param $memberId Member ID (eg. member email)
     *
     * @return void
     */
    public function actionDelete($groupKey, $memberId)
    {
        $module = $this->module;
        $client = $module->client;
        $service = new \Google_Service_Directory($client);

        try {
            $service->members->delete($groupKey, $memberId);
            $this->stdout('Successfully deleted a member' . PHP_EOL, Console::FG_GREEN);
        } catch (\Google_Service_Exception $e) {
            $this->stdout('Something went wrong when tried to delete a member: ' . $e->getMessage() . PHP_EOL, Console::FG_RED);
        }
    }

    private function getModule()
    {
        return Yii::$app->controller->module;
    }
}
