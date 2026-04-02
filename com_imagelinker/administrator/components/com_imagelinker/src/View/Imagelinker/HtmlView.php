<?php
/**
 * @package  Imagelinker Component
 * @version  1.3
 * @license  GNU General Public License version 2
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
    protected $mediaFolders;

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
        if (!$user->authorise('core.manage', 'com_imagelinker'))
        {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return false;
        }

        // Get the form from the model
        $model = $this->getModel();
        $this->form = $model->getForm();

        if (!$this->form)
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_IMAGELINKER_FORM_NOT_LOADED'), 'error');
            return false;
        }

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_IMAGELINKER'), 'image imagelinker');

        $user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
        if ($user->authorise('core.admin', 'com_imagelinker'))
        {
            ToolbarHelper::preferences('com_imagelinker', '500');
        }

    }
}