<?php
declare(strict_types=1);

use Phalcon\Mvc\Controller;

class ControllerBase extends Controller
{
    public $auth;
    
    public function initialize()
    {
        if ($this->request->isAjax()) {
            return;
        }

        $headerCollection = $this->assets->collection('headerCss');
        $headerCollection->addCss('css/simple-sidebar.css');
        $headerCollection->addCss('https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css');
        $headerCollection->addCss('https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.min.css');

        $headerJsCollection = $this->assets->collection('headerJs');
        $headerJsCollection->addJs('https://cdn.jsdelivr.net/npm/vue@2.6.12/dist/vue.js');

        $footerCollection = $this->assets->collection('footerJs');
        $footerCollection->addJs('https://code.jquery.com/jquery-3.5.1.min.js');
        $footerCollection->addJs('https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.bundle.min.js');
        $footerCollection->addJs('https://unpkg.com/popper.js@1');
        $footerCollection->addJs('https://unpkg.com/tippy.js@5');
        $footerCollection->addJs('https://cdnjs.cloudflare.com/ajax/libs/noty/3.1.4/noty.min.js');






        $this->view->auth = $this->auth;
        $this->view->titleTag = 'Voting';
        $this->view->collapseNav = true;

    }

    public function beforeExecuteRoute($dispatcher)
    {
        $auth = $this->session->get('auth');
        $this->auth = $auth;
    }
}


