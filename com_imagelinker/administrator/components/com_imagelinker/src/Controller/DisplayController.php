<?php
/**
 * @package  Imagelinker Component
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

class DisplayController extends BaseController
{
    // Specify the default view to load when no view is explicitly requested in the URL
    protected $default_view = 'imagelinker';

    public function display($cachable = false, $urlparams = false)
    {
        $viewName = $this->input->get('view', $this->default_view);
        $viewLayout = $this->input->get('layout', 'default');

        $view = $this->getView($viewName, Factory::getDocument()->getType(), 'Administrator');
        $model = $this->getModel('Imagelinker', 'Administrator');

        if ($model)
        {
            $view->setModel($model, true);
        }

        $view->setLayout($viewLayout);
        $view->display();
    }
}