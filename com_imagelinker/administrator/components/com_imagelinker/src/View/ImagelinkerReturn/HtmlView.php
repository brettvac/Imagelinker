<?php
/**
 * @package  Imagelinker Component
 * @license  GNU General Public License version 2
 */

namespace Naftee\Component\Imagelinker\Administrator\View\ImagelinkerReturn;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Factory;

class HtmlView extends BaseHtmlView
{
    protected $brokenImages;
    protected $form;

    public function display($tpl = null)
    {
        $user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
        if (!$user->authorise('core.manage', 'com_imagelinker'))
        {
            Factory::getApplication()->enqueueMessage(Text::_('JERROR_ALERTNOAUTHOR'), 'error');
            return false;
        }
               
        $model = $this->getModel();
        $this->form = $model->getForm();

        if (!$this->form)
        {
            Factory::getApplication()->enqueueMessage(Text::_('COM_IMAGELINKER_FORM_NOT_LOADED'), 'error');
            return false;
        }
        
        // Get the list of unlinked images from the user state (populated by scan action)
        //$this->unlinkedImages = Factory::getApplication()->getUserState('com_imagelinker.unlinked_images', []); 
        $data = Factory::getApplication()->getUserState('com_imagelinker.data', new \stdClass);
        $this->unlinkedImages = $data->unlinked_images ?? [];

        $this->addToolbar();
        parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_IMAGELINKER_RESULTS_TITLE'), 'image imagelinker');
        
        ToolbarHelper::cancel('imagelinker.cancel', 'JTOOLBAR_CANCEL');

        $user = Factory::getApplication()->getIdentity() ?: Factory::getUser();
        if ($user->authorise('core.admin', 'com_imagelinker'))
        {
            ToolbarHelper::preferences('com_imagelinker', '500');
        }

    }
}