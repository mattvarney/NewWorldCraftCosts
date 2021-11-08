<?php
declare(strict_types=1);

class IndexController extends ControllerBase
{
    public function indexAction()
    {
        $this->view->titleTag = 'Craft XP Calculator';

        $this->view->recipes = file_get_contents ( '../data/recipes.json');
        $this->view->resources = file_get_contents ( '../data/resources.json');
    }

}

