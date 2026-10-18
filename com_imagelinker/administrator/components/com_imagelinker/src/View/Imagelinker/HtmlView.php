<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  Imagelinker
 * @version     1.5
 * @license     GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\View\Imagelinker;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    protected $form;

    public function display($tpl = null)
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();
        
        if (!$user->authorise('core.manage', 'com_imagelinker'))
        {
            $app->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return false;
        }

        // Get the form from the model
        $model = $this->getModel();
        $this->form = $model->getForm();

        if (!$this->form)
        {
            $app->enqueueMessage(Text::_('COM_IMAGELINKER_FORM_NOT_LOADED'), 'error');
            return false;
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_IMAGELINKER'), 'image imagelinker');

        $app  = Factory::getApplication();
        $user = $app->getIdentity();
        
        if ($user->authorise('core.admin', 'com_imagelinker'))
        {
            ToolbarHelper::preferences('com_imagelinker', '500');
        }

    }
}