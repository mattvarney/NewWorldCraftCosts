<?php

namespace Voting\App\Plugins;

use Phalcon\Events\Event;
use Phalcon\Mvc\Dispatcher;
use Phalcon\Acl\Adapter\Memory;
use Phalcon\Acl\Role;
use Phalcon\Acl\Enum;
use Phalcon\Acl\Component;

/**
 * Security plugin used for every request. Uses an ACL to determine access for
 * users.
 */
class Security extends \Phalcon\Di\Injectable
{

    /**
     * Returns the access control list
     *
     * @param bool forceNewAcl - set to true if you want to create a new acl nomatter what
     *
     * @return object - Access control list
     */
    public function getAcl()
    {
        $acl = new Memory();

        $acl->setDefaultAction(Enum::DENY);

        $roles = array(
            'guests' => new Role('Guests'),
            'admins' => new Role('Admins'),
        );

        foreach ($roles as $role) {
            $acl->addRole($role);
        }

        $publicComponents = array(
            'index' => [
                'index',
                'pullSpreadsheetData'
            ],
        );

        foreach ($publicComponents as $resource => $actions) {
            $actions = array_map('strtolower', $actions);
            $acl->addComponent(new Component($resource), $actions);
            foreach ($actions as $action) {
                $acl->allow('Guests', $resource, $action);
                $acl->allow('Admins', $resource, $action);
            }
        }

        $adminComponents = array(

        );

        foreach ($adminComponents as $resource => $actions) {
            $actions = array_map('strtolower', $actions);
            $acl->addComponent(new Component($resource), $actions);
            foreach ($actions as $action) {
                $acl->allow('Admins', $resource, $action);
            }
        }

        return $acl;
    }

    /**
     * Method that is called before every request. Checks the users
     * authorization level and checks the ACL if the users has access to the
     * requested destination.
     *
     * @param  Event      $event      - The event object for the request
     * @param  Dispatcher $dispatcher - Dispatcher object associated with the
     *                                request
     * @return boolean
     */
    public function beforeDispatch(Event $event, Dispatcher $dispatcher)
    {
        $this->response->setHeader('Referrer-Policy', 'no-referrer');
        $auth = $this->session->get('auth');
        if (!$auth) {
            $role = 'Guests';
        } else if ($auth['isAdmin'] === 1) {
            $role = 'Admins';
        }
        $role = 'Guests';
        // Get active controller/action from dispatcher
        $controller = $dispatcher->getControllerName();
        $action = $dispatcher->getActionName();

        $accessInfo = array(
            'role' => $role,
            'controller' => $controller,
            'action' => $action,
            'routeExists' => 1,
        );

        // get ACL list
        $acl = $this->getAcl();

        // check if the role has access to the controller (resource)
        $allowed = $acl->isAllowed($role, strtolower($controller), strtolower($action));

        if ($allowed != Enum::ALLOW) {
            if ($this->request->isAjax()) {
                http_response_code(403);
                echo json_encode((array(
                    'isError' => 1,
                    'errorMessage' => 'You are not authorized.'
                )));
                error_log("You are not authorized.");

                exit();
            } else {
                /**
                 * if user is being directed to log in from a page other than
                 * a login page and the reason they don't have access is
                 * because they're not logged in, store where they came from
                 * so we can send them back there if they successfully sign in
                 */
                $message = ($role == 'Guests') ?
                    "You must be logged in to access this area." :
                    "You do not have access to this area";
                $this->flashSession->error($message);

                if ($role == 'Guests') {
                    $this->session->set('forwardTo', array(
                        'controller' => $controller,
                        'action' => $action
                    ));
                    $dispatcher->forward(array(
                        'controller' => 'auth',
                        'action' => 'login',
                    ));
                }
            }
            return false;
        }
    }
}
