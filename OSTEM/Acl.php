<?php

namespace OSTEM;

use Zend\Permissions\Acl\Acl as ZendAcl;

/**
 * OSTEM website ACLs
 */
class Acl extends ZendAcl
{
    public function __construct()
    {
        // Application roles 
        $this->addRole('guest');

        // member role extends guest, meaning the member role
        // will get all of the guest role permissions by default
        //$this->addRole('member', 'guest');
        $this->addRole('admin');

        // Application resources (slim route patterns)
        $this->addResource('/');
        $this->addResource('/login');
        $this->addResource('/logout');
        //$this->addResource('/member');

        $this->addResource('/admin');
        $this->addResource('/admin/update');
        $this->addResource('/admin/profile');
        $this->addResource('/admin/newsletter/draft');
        $this->addResource('/admin/newsletter/send');

        $this->addResource('/subscribe');
        $this->addResource('/unsubscribe');
        $this->addResource('/unsubscribe/:uuid');

        // Application permissions

        // Allow or deny a role's access to resources. The third argument
        // is 'privilege'. We're using HTTP method as 'privilege'.
        $this->allow('guest', '/', 'GET');
        $this->allow('guest', '/login', ['GET', 'POST']);
        $this->allow('guest', '/logout', 'GET');

        $this->allow('guest', '/subscribe', ['GET', 'POST']);
        $this->allow('guest', '/unsubscribe/:uuid', 'GET');

        //$this->allow('member', '/member', 'GET');

        // This allows admin access to everything
        $this->allow('admin');
    }
}
